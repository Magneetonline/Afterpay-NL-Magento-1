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
 
class Afterpay_Afterpay_Model_Request_Refund extends Afterpay_Afterpay_Model_Request_Abstract
{
    protected $_invoice;
    protected $_payment;
    protected $_creditmemo;
    protected $_isPartial = false;
    protected $_appliedStoreCreditsToRefund;
    protected $_storeCreditsToRefund;
    protected $_appliedRewardPointsToRefund;
    protected $_rewardPointsToRefund;

    public function setInvoice($invoice)
    {
        $this->_invoice = $invoice;
        return $this;
    }
    
    public function getInvoice()
    {
        return $this->_invoice;
    }
    
    public function setPayment($payment)
    {
        $this->_payment = $payment;
        return $this;
    }
    
    public function getPayment()
    {
        return $this->_payment;
    }
    
    public function setCreditmemo($creditmemo)
    {
        $this->_creditmemo = $creditmemo;
        return $this;
    }
    
    public function getCreditmemo()
    {
        return $this->_creditmemo;
    }
    
    public function setIsPartial($isPartial)
    {
        $this->_isPartial = $isPartial;
        return $this;
    }
    
    public function getIsPartial()
    {
        return $this->_isPartial;
    }
    
    public function loadInvoiceByTransactionId($transactionId)
    {
        foreach ($this->getOrder()->getInvoiceCollection() as $invoice) {
            if ($invoice->getTransactionId() == $transactionId) {
                $invoice->load($invoice->getId()); // to make sure all data will properly load (maybe not required)
                return $invoice;
            }
        }
        return false;
    }
    
    protected function _construct() 
    {
        $this->setHelper(Mage::helper('afterpay'));
    }
    
    public function sendRefundRequest()
    {
        $method = $this->_order->getPayment()->getMethod();

        $country = (string) Mage::getStoreConfig('afterpay/afterpay_' . $method . '/portfolio_country', Mage::app()->getStore()->getId());
        $this->setCountry($country);    
        
        $testMode = (bool) Mage::getStoreConfig('afterpay/afterpay_' . $method . '/mode', Mage::app()->getStore()->getId());

        $this->setTestMode($testMode);
        
        $this->_isRefundAllowed();
        $this->_isRefundPartial();
        
        $responseModel = Mage::getModel('afterpay/response_refund');
        
        $this->_debugEmail .= 'Chosen portfolio: ' . $this->_method . "\n";

        //if no method has been set (no payment method could identify the chosen method) process the order as if it had failed
        if (empty($this->_method)) {
            $this->_debugEmail .= "No method was set! \n";
            
            $responseModel->setResponse(false)
                          ->setResponseXML(false)
                          ->setDebugEmail($this->_debugEmail);
            
            try {
                return $responseModel->processResponse();
            } catch (Exception $exception) {
                $responseModel->sendDebugEmail();
                $this->logException($exception);
                return false;
            }
        }

        $this->_debugEmail .= "\n";
        //forms an array with all payment-independant variables (such as merchantkey, order id etc.) which are required for the transaction request
        $this->_addShopVariables();
        $this->_addTransactionKey();
        $this->_addPortfolioVariables();
        $this->_addOrderVariables(true);
        $this->_addRefundVariables();
        
        $this->_debugEmail .= "Firing request events. \n";
        //event that allows individual payment methods to add additional variables such as bankaccount number
        //currently this is not used, however developers may use this event to easily modify the values sent to AfterPay
        Mage::dispatchEvent('afterpay_refund_request_addcustomvars', array('request' => $this, 'order' => $this->_order));

        $this->_debugEmail .= "Events fired! \n";

        //clean the array for a soap request
        $this->setVars($this->_cleanArrayForSoap($this->getVars()));

        $this->_debugEmail .= "Variable array:" . var_export($this->_vars, true) . "\n\n";
        $this->_debugEmail .= "Building SOAP request... \n";

        //send the transaction request using SOAP
        $soap = Mage::getModel('afterpay/soap_refund');
        $soap->setvars($this->getVars())
             ->setTestMode($this->getTestMode())
             ->setMethod($this->getMethod())
             ->setIsPartial($this->getIsPartial())
             ->setCountry($this->getCountry())
             ->setUsesoapservices(true);
        
        list($response, $responseXML, $requestXML) = $soap->refundRequest();
        
        $this->_debugEmail .= "The SOAP request has been sent. \n";
        
        if (!is_object($requestXML) || !is_object($responseXML)) { 
            $this->_debugEmail .= "Request or response was not an object \n";
        } else {
            $this->_debugEmail .= "Request: " . var_export($requestXML->saveXML(), true) . "\n";
            $this->_debugEmail .= "Response: " . var_export($response, true) . "\n";
            $this->_debugEmail .= "Response XML:" . var_export($responseXML->saveXML(), true) . "\n\n";
        }

        $this->_debugEmail .= "Processing response... \n";
        //process the response
        $responseModel->setResponse($response)
                      ->setResponseXML($responseXML)
                      ->setDebugEmail($this->getDebugEmail())
                      ->setRequest($this)
                      ->setOrder($this->getOrder());
        
        try {
            return $responseModel->processResponse();
        } catch (Exception $exception) {
            $responseModel->sendDebugEmail();
            $this->logException($exception);
            return false;
        }
    }
    
    protected function _isRefundAllowed()
    {
        $captureModeUsed = $this->_order->getAfterpayCaptureMode();
        $captured = $this->_order->getAfterpayCaptured();
        $this->_storeCreditsToRefund = (int) $this->_creditmemo->getBaseCustomerBalanceTotalRefunded() * 100;
        $this->_rewardPointsToRefund = (int) $this->_creditmemo->getRewardPointsBalanceRefund() * 100;

        if ($captureModeUsed == 1 && !$captured) {
            Mage::throwException($this->_helper->__('This order has not yet been captured by AfterPay.'));
        }

        if (!Mage::getStoreConfig('afterpay/afterpay_refund/enabled', Mage::app()->getStore()->getId())) {
            Mage::throwException($this->_helper->__('Online refunding is disabled. Please us offline refunding or enable online refunding in the config.'));
        }

        if($this->_storeCreditsToRefund > 0)
        {
            Mage::throwException($this->_helper->__('Refund to store credits or reward points is not possible, using AfterPay online refund. First do an offline refund for the desired amount to credit / award points. Then do an online AfterPay refund for the remaining amount, if applicable.'));
        }

        if($this->_rewardPointsToRefund > 0)
        {
            Mage::throwException($this->_helper->__('Refund to store credits or reward points is not possible, using AfterPay online refund. First do an offline refund for the desired amount to credit / award points. Then do an online AfterPay refund for the remaining amount, if applicable.'));
        }
    }
    
    /**
     * Checks if the refund is full or partial. Does this by comparing values in the creditmemo with corresponding values in the order
     * BUGFIX: REMOVED FULL REFUND POSSIBILiTY
     */
    protected function _isRefundPartial()
    {
        $this->setIsPartial(true);
        return true;
    }
    
    /**
     * Overloads parent function in order to add refund adjustment lines
     * 
     * N.B. all amount values are changed to negative values, except positive adjustment amount
     */
    protected function _getOrderLines()
    {
        $orderLines = array();
        $this->_appliedStoreCreditsToRefund = (int) round($this->_creditmemo->getBaseCustomerBalanceAmount() * 100, 0);
        $this->_appliedRewardPointsToRefund = (int) round($this->_creditmemo->getBaseRewardCurrencyAmount() * 100, 0);

        foreach ($this->_creditmemo->getAllItems() as $orderItem) {

            if (empty($orderItem) || $orderItem->hasParentItemId() || $orderItem->getPriceInclTax() == 0 ) {
                continue;
            }

            $orderItemQty = $orderItem->getQty();

            // If product cannot be loaded by Id get the child order item
            if(is_null($orderItem->getId())) {
                $orderItem = $orderItem->getOrderItem();
            }

            // Do not take parent product of bundle
            if($orderItem->getProductType() == 'bundle') {
                continue;
            }

            $vatCategory = $this->_getTaxCategory($orderItem->getProduct()->getTaxClassId());
            $unitPrice = (int) round($orderItem->getPriceInclTax() * 100 * -1, 0);

            // multiply times quantity to get correct amount spent
            $unitPrice *= $orderItemQty;

            $line = array(
                'articleDescription' => "Refund: " . $orderItem->getName(),
                'articleId'          => "REFUND",
                'unitPrice'          => $unitPrice,
                'vatCategory'        => $vatCategory,
                'quantity'           => 1,
            );

            $orderLines[] = $line;
        }

        $orderLines[] = $this->_addShippingLine();
        $orderLines[] = $this->_addDiscountLine();
        $orderLines[] = $this->_addPaymentFeeLine();
        $orderLines[] = $this->_addPositiveAdjustmentLine();
        $orderLines[] = $this->_addAppliedStorecreditsLine();
        $orderLines[] = $this->_addAppliedRewardPointsLine();

        return $orderLines;
    }

    protected function _addShippingLine()
    {
        $shipping  = $this->_creditmemo->getBaseShippingAmount();
        $unitPrice = round(($shipping + $this->_creditmemo->getBaseShippingTaxAmount()) * 100 * -1, 0);

        if (!empty($shipping)) {
            $shippingLine = array(
                'articleDescription' => 'Refund: Verzendkosten',
                'articleId'          => 'REFUND',
                'unitPrice'          => $unitPrice,
                'vatCategory'        => $this->_getTaxCategory(Mage::getStoreConfig('tax/classes/shipping_tax_class', Mage::app()->getStore()->getId())),
                'quantity'           => 1,
            );

            return $shippingLine;
        }
        return false;
    }

    protected function _addPaymentFeeLine()
    {
        // Check if AfterPay Fee is used for service fee
        if (Mage::helper('core')->isModuleEnabled('Afterpay_Afterpayfee'))
        {
            $paymentFee = $this->_order->getAfterpayfeeAmount();

            if (!empty($paymentFee)) {
                $paymentFeeLine = array(
                    'articleDescription' => Mage::getStoreConfig('afterpay/afterpay_afterpayfee/afterpayfee_label', $this->_order->getStoreId()),
                    'articleId'          => 'FEE',
                    'unitPrice'          => round($paymentFee * 100 * -1, 0),
                    'vatCategory'        => 1,
                    'quantity'           => 1,
                );

                return $paymentFeeLine;
            }
        }

        // Check if Fooman Surcharge is used for service fee
        if (Mage::helper('core')->isModuleEnabled('Fooman_Surcharge'))
        {
            $paymentFee = $this->_creditmemo->getBaseFoomanSurchargeAmount() + $this->_creditmemo->getBaseFoomanSurchargeTaxAmount();

            if (!empty($paymentFee)) {
                $paymentFeeLine = array(
                    'articleDescription' => $this->_order->getFoomanSurchargeDescription(),
                    'articleId'          => 'FEE',
                    'unitPrice'          => round($paymentFee * 100 * -1, 0),
                    'vatCategory'        => 1,
                    'quantity'           => 1,
                );

                return $paymentFeeLine;
            }
        }

        // Check if Mageworx Multifees is used for service fee
        if (Mage::helper('core')->isModuleEnabled('MageWorx_MultiFees'))
        {
            $paymentFee = (float) ($this->_order->getMultifeesAmount());

            if (!empty($paymentFee)) {
                $paymentFeeLine = array(
                    'articleDescription' => 'Extra kosten',
                    'articleId'          => 'FEE',
                    'unitPrice'          => round($paymentFee * 100 * -1, 0),
                    'vatCategory'        => 1,
                    'quantity'           => 1,
                );

                return $paymentFeeLine;
            }
        }

        return false;
    }
    
    protected function _addDiscountLine()
    {
        $vatCategory = $this->_getTaxCategory(Mage::getStoreConfig('afterpay/afterpay_tax/discount_tax_class', $this->_order->getStoreId()));
        $discount = $this->_creditmemo->getBaseDiscountAmount();
        if (!empty($discount)) {
            $discountLine = array(
                'articleDescription' => 'Korting',
                'articleId'          => 'DISCOUNT',
                'unitPrice'          => round($discount * 100 * -1, 0),
                'vatCategory'        => $vatCategory,
                'quantity'           => 1,
            );

            return $discountLine;
        }

        return false;
    }

    protected function _addPositiveAdjustmentLine()
    {
        $positiveAdjustment = $this->_creditmemo->getBaseAdjustmentPositive();
        if (!empty($positiveAdjustment)) {
            $adjustmentLine = array(
                'articleDescription' => 'Refund',
                'articleId'          => 'REFUND',
                'unitPrice'          => round($positiveAdjustment * 100 * -1, 0),
                'vatCategory'        => 4,
                'quantity'           => 1,
            );

            return $adjustmentLine;
        }
        return false;
    }

    protected function _addAppliedStorecreditsLine()
    {
        $storecredits = $this->_appliedStoreCreditsToRefund;
        if (!empty($storecredits )) {
            $storecreditsLine = array(
                'articleDescription' => 'Store credits gebruikt in order',
                'articleId'          => 'STORE CREDITS',
                'unitPrice'          => round($storecredits, 0),
                'vatCategory'        => 4,
                'quantity'           => 1,
            );

            return $storecreditsLine;
        }
        return false;
    }

    protected function _addAppliedRewardPointsLine()
    {
        $rewardpoints = $this->_appliedRewardPointsToRefund;
        if (!empty($rewardpoints)) {
            $storecreditsLine = array(
                'articleDescription' => 'Reward points gebruikt in order',
                'articleId'          => 'REWARD POINTS',
                'unitPrice'          => round($rewardpoints, 0),
                'vatCategory'        => 4,
                'quantity'           => 1,
            );

            return $storecreditsLine;
        }
        return false;
    }

    protected function _addRefundVariables()
    {
        $array = array(
            'invoiceId' => $this->_invoice->getIncrementId(),
        );

        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }

        $this->_debugEmail .= "Refund variables added! \n";
    }

    protected function _addTransactionKey()
    {
        $array = array(
            'parentTransactionReference' => $this->_order->getAfterpayOrderReference(),
        );

        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }

        $this->_debugEmail .= "Portfolio variables added! \n";
    }
}