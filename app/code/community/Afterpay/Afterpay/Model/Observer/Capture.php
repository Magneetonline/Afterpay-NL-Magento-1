<?php 
/**
 * Copyright (c) 2011-2017  arvato Finance B.V.
 *
 * AfterPay reserves all rights in the Program as delivered. The Program
 * or any portion thereof may not be reproduced in any form whatsoever without
 * the written consent of AfterPay.
 *
 * Disclaimer:
 * THIS NOTICE MAY NOT BE REMOVED FROM THE PROGRAM BY ANY USER THEREOF.
 * THE PROGRAM IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE PROGRAM OR THE USE OR OTHER DEALINGS
 * IN THE PROGRAM.
 *
 * @category    AfterPay
 * @package     Afterpay_Afterpay
 * @copyright   Copyright (c) 2011-2017 arvato Finance B.V.
 */
 
class Afterpay_Afterpay_Model_Observer_Capture extends Mage_Core_Model_Abstract
{
    public function sales_order_invoice_register(Varien_Event_Observer $observer)
    {
        //to prevent the script from running twice
        if (Mage::registry('AfterpayCaptureStarted')) {
            Mage::unregister('AfterpayCaptureStarted');
            return $this;
        }
        
        // Only do capture on invoice register when capture mode is not manual
        if (Mage::getStoreConfig('afterpay/afterpay_capture/capture_mode', Mage::app()->getStore()->getId()) != '1') {
            return $this->_capture($observer->getOrder(), $observer->getInvoice());
        } else {
            return $this;
        }
    }
    
    public function sales_order_payment_capture(Varien_Event_Observer $observer)
    {
        Mage::register('AfterpayCaptureStarted', 1);
        
        return $this->_capture($observer->getInvoice()->getOrder(), $observer->getInvoice());
    }
    
    public function sales_order_invoice_save_before(Varien_Event_Observer $observer)
    {
        $invoice = $observer->getInvoice();
        
        if (!$invoice->getTransactionId() && $invoice->getOrder()->getAfterpayOrderReference()) {
            $invoice->setTransactionId($invoice->getOrder()->getAfterpayOrderReference());
        }
        
        return $this;
    }
    
    public function sales_order_shipment_save_after(Varien_Event_Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        
        try {
            
            if (Mage::getStoreConfig('afterpay/afterpay_general/auto_invoice', $order->getStoreId()) !== 'yes-shipping') {
                return false;
            }

            Mage::log('sales_order_shipment_save_after called', null, 'Afterpay_Observer.log', true);

            
            if($order->canInvoice())
            {   
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();
            }
        } catch (Exception $e) {
            $order->addStatusHistoryComment(
                'AfterPay: Cannot auto create invoice based on shipping. Exception message: '.
                $e->getMessage(), false);
            $order->save();
        }
    }
    
    public function sales_order_save_after(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (Mage::getStoreConfig('afterpay/afterpay_general/auto_invoice', $order->getStoreId()) !== 'yes-status') {
            return false;
        }
        
        try {
            
            Mage::log('sales_order_save_after called', null, 'Afterpay_Observer.log', true);
    
            $changeOnStatus = Mage::getStoreConfig('afterpay/afterpay_general/auto_invoice_status', $order->getStoreId());
            $currentStatus = $order->getStatus();
            $originalStatus = $order->getOrigData('status');
    
            Mage::log('Change on status: ' . $changeOnStatus, null, 'Afterpay_Observer.log', true);
            Mage::log('Current status: ' . $currentStatus, null, 'Afterpay_Observer.log', true);
            Mage::log('Original status: ' . $originalStatus, null, 'Afterpay_Observer.log', true);
    
            if ($currentStatus == $changeOnStatus && $originalStatus != $changeOnStatus) {
                if($order->canInvoice())
                {
                    $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                    $invoice->register();
                    $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
                    $transactionSave->save();
                }
            }
        } catch (Exception $e) {
            $order->addStatusHistoryComment(
                'AfterPay: Cannot auto create invoice based on status. Exception message: '.
                $e->getMessage(), false);
            $order->save();
        }
        return $this;
    }
    
    protected function _capture($order, $invoice)
    {    
        try {  
            if ($this->_captureIsAllowed($order, $invoice) !== true) {
                return $this;
            }
        
            $captureRequest = Mage::getModel('afterpay/request_capture'); 
            $captureRequest->setOrder($order)
                           ->setMethod($order->getPayment()->getMethod())
                           ->setInvoice($invoice);
            
            $result = $captureRequest->sendCaptureRequest();
        } catch (Exception $exception) {
            $invoice->cancel()->save();
            
            Mage::getSingleton('adminhtml/session')->addError($exception->getMessage());
            Mage::throwException($exception->getMessage());
        }
        
        if ($result === false) {
            $invoice->cancel()->save();
            
            Mage::throwException('Unable to capture this invoice');
        }
        
        return $this;
    }
    
    protected function _captureIsAllowed($order, $invoice)
    {
        $paymentMethodCode = $order->getpayment()->getMethod();
        
        if (strpos($paymentMethodCode, 'portfolio') === false) {
            return false;
        }
        
        if (Mage::getStoreConfig('afterpay/afterpay_capture/capture_mode', Mage::app()->getStore()->getId()) === '0') {
            return false;
        }
        
        if (
            $invoice->getBaseGrandTotal() - $order->getBaseGrandTotal() > 0.01 
            || $invoice->getBaseGrandTotal() - $order->getBaseGrandTotal() < -0.01
        ) {
            Mage::throwException('Can only capture full invoices. Partial invoices cannot be captured by AfterPay.');
            return false;
        }
        
        if (
            (isset($_POST['invoice']) && isset($_POST['invoice']['capture_case']))
            && $_POST['invoice']['capture_case'] != 'online'
        ) {
            return false;
        }
        
        return true;
    }
}