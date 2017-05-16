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
 
 class Afterpay_Afterpay_Model_Observer_BackendOrder extends Mage_Core_Model_Abstract
{
    public function checkout_submit_all_after(Varien_Event_Observer $observer)
    {
        $order = $observer->getOrder();
        $method = $order->getPayment()->getMethod();
        $allowedPaymentMethods = Mage::helper('afterpay')->getAfterPayPaymentMethods();
        
        if (!in_array($method, $allowedPaymentMethods)) {
            return $this;
        }
        
        try {
            $request = Mage::getModel('afterpay/request_backendOrder');
            $request->setOrder($order)
                    ->setMethod($method)
                    ->setAdditionalFields($order->getPayment()->getMethodInstance()->getInfoInstance()->getAdditionalInformation())
                    ->setTestMode((bool) Mage::getStoreConfig('afterpay/afterpay_' . $method . '/mode', Mage::app()->getStore()->getId()))
                    ->setOrderBillingInfo()
                    ->setOrderShippingInfo();
                    
            $portfolioType = Mage::getStoreConfig('afterpay/afterpay_' . $method . '/portfolio_type', Mage::app()->getStore()->getId());
            if ($portfolioType == 'B2B') {
                $request->setIsB2B(true);
            }
            
            $response  = $request->sendRequest();
        } catch (Exception $exception) {
            $response = false;
            Mage::getSingleton('core/session')->addError(
                Mage::helper('afterpay')->__($exception->getMessage())
            );
            Mage::throwException($exception->getMessage());
        }
        
        return $this;
    }
}