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
 
 class Afterpay_Afterpay_Model_Portfolios_Abstract extends Mage_Payment_Model_Method_Abstract
{
    protected $_payment;
    
    public function setPayment($payment)
    {
        $this->_payment = $payment;
    }
    
    public function getPayment()
    {
        return $this->_payment;
    }
    
    public $allowedCurrencies = array(
        'EUR',
    );

    public function getAllowedCurrencies()
    {
        return $this->allowedCurrencies;
    }

    public function setAllowedCurrencies($allowedCurrencies)
    {
        $this->allowedCurrencies = $allowedCurrencies;
    }
    
    protected $_formBlockType = 'afterpay/portfolios_checkout_form';
    protected $_infoBlockType = 'afterpay/portfolios_info';
    
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;
    protected $_canSaveCc                 = false;

    /**
     * Normally for payment methods the title is stored in payment/paymentmethod/title
     * AfterPay however, stores it in afterpay/afterpay_portfolio_code/portfolio_label
     * 
     * @see Mage_Payment_Model_Method_Abstract::getTitle()
     */
    public function getTitle()
    {
        $title = Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/portfolio_label', Mage::app()->getStore()->getId());
        return $title;
    }

    public function getFootnote()
    {
        $footnote = Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/portfolio_footnote', Mage::app()->getStore()->getId());
        return $footnote;
    }
    
    public function getExtendedInformation()
    {
        $extendedInformation =  Mage::getStoreConfig('afterpay/afterpay_' . $this->_code  . '/portfolio_extended_information', Mage::app()->getStore()->getId());
        return $extendedInformation;
    }
    
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('afterpay/checkout/checkout', array('_secure' => true, 'method' => $this->_code));
    }
    
    /**
     * Several checks to determine whether or not the selected portfolio (payment method) should be displayed to
     * the current customer.
     * 
     * @see Mage_Payment_Model_Method_Abstract::isAvailable()
     */
    public function isAvailable($quote = null)
    {
        //if ther quote is empty, these checks will fail anyway
        if (!is_object($quote)) {
            return false;
        }
        
        $storeId = Mage::app()->getStore()->getId();
        
        $mainConfigActive = (bool) Mage::getStoreConfig('afterpay/afterpay_general/active', $storeId);
        $portfolioActive  = (bool) Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/active', $storeId);
        
        //checks if the module is enabled and if the selected portfolio is enabled
        if (!$mainConfigActive || !$portfolioActive) {
            return false;
        }
        
        $areaAllowed = Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/area', $storeId);
        
        //check if the portfolio is available in the current shop area (frontend or backend)
        if ($areaAllowed == 'backend'
            && !Mage::helper('afterpay')->isAdmin()
        ) {
            return false;
        } elseif ($areaAllowed == 'frontend'
            && Mage::helper('afterpay')->isAdmin()
        ) {
            return false;
        }
        
        $portfolioIpCheckEnabled  = (bool) Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/limit_by_ip', $storeId);
        $allowedIps               = array_map('trim', explode(',', Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/limit_by_ip_ips', $storeId)));
        $currentIp                = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $currentIp            = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $currentIp            = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        // If reversed iprestriction is filled with ip addresses then the payment method is not available for that ip adresses
        $portfolioReversedIpCheckEnabled    = (bool) Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/reversed_iprestriction', $storeId);
        $disallowedIps                      = array_map('trim', explode(',', Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/reversed_iprestriction_ips', $storeId)));
        
        if($portfolioReversedIpCheckEnabled)
        {
            if (in_array($currentIp, $disallowedIps)) { return false; }
        }
        else 
        {
            //checks if the IP-restriction option is enabled and if so, if the current user's IP is allowed
            if (
                ($portfolioIpCheckEnabled)
                 && (
                    !in_array($currentIp, $allowedIps) 
                    )
                )
            {
                return false;
            }
        
        }
        
        $minAmountAllowed = (float) Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/portfolio_min_amount', $storeId);
        $maxAmountAllowed = (float) Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/portfolio_max_amount', $storeId);
        
        //checks if the current quote's base grandtotal falls within the minimum and maximum allowed for this portfolio
        if (!empty($maxAmountAllowed)
            && ($quote->getBaseGrandTotal() < $minAmountAllowed || $quote->getBaseGrandTotal() > $maxAmountAllowed)
            ) 
        {
            return false;
        }
        
        $specificCustomerGroupsAllowed = (bool) Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/allowspecific_customers', $storeId);
        $customerGroupsAllowed         = explode(',', Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/specificcustomers', $storeId));
        $groupId                       = Mage::getSingleton('customer/session')->getCustomerGroupId();
        $group                         = Mage::getSingleton('customer/group')->load($groupId)->getData('customer_group_code');
        
        //checks if the current customer's customer group is allowed to use this portfolio
        if ($specificCustomerGroupsAllowed
            && !in_array($group, $customerGroupsAllowed)
            )
        {
            return false;
        }
        
        $specificCountry  = (bool) Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/allowspecific', $storeId);
        $countriesAllowed = explode(',',Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/specificcountry', $storeId));
        $countrySelected  = $quote->getBillingAddress()->getCountry();
        
        //check if the country specified in the billing address is allowed to use this payment method
        if ($specificCountry && !in_array($countrySelected, $countriesAllowed)) {
            return false;
        }
        
        //check not allowed shipment methods
        $shippingMethodsNotAllowed = explode(',',Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/notallowedshippingmethods', $storeId));
        $shippingMethodSelected = $quote->getShippingAddress()->getShippingMethod();
        if (is_array($shippingMethodsNotAllowed) && !empty($shippingMethodSelected) && in_array($shippingMethodSelected, $shippingMethodsNotAllowed)) {
            return false;
        }
        
        //all checks are positive, allow the portfolio to be used
        return parent::isAvailable($quote); 
    }
    
    public function refund(Varien_Object $payment, $amount)
    {
        $this->_canRefund($payment);
        
        if ($this->_isCancel($payment)) {
            return $this->_cancel($payment, $amount);
        } else {
            return $this->_refund($payment, $amount);
        }
        
        try {
            $refundRequest = Mage::getModel('afterpay/request_refund', array('payment' => $payment, 'amount' => $amount));
            $success = $refundRequest->sendRefundRequest();
        } catch (Exception $exception) {
            $payment->getCreditmemo()->cancel();
            Mage::getModel('afterpay/abstract')->logException($exception);
            Mage::getSingleton('adminhtml/session')->addError('An error occurred while processing your refund request: ' . $exception->getMessage());
            Mage::throwException($exception->getMessage());
        }
        
        if ($success === false) {
            $payment->getCreditmemo()->cancel();
            Mage::getSingleton('adminhtml/session')->addError('An error occurred while processing your refund request.');
            Mage::throwException('An error occurred while processing your refund request.');
        }
        
        return $this;
    }
    
    protected function _refund($payment, $amount) 
    {
        try {
            $refundRequest = Mage::getModel('afterpay/request_refund');
                        
            $refundRequest->setpayment($payment)
                          ->setOrder($payment->getOrder())
                          ->setCreditmemo($payment->getCreditmemo())
                          ->setMethod($payment->getMethod());
                          
            $invoice = $refundRequest->loadInvoiceByTransactionId($payment->getOrder()->getAfterpayOrderReference());
            
            if ($invoice === false) {
                Mage::throwException($this->_getHelper()->__('Refund action is not available.'));
            }
            $refundRequest->setInvoice($invoice);
            
            $success = $refundRequest->sendRefundRequest();
        } catch (Exception $exception) {
            Mage::getModel('afterpay/abstract')->logException($exception);
            Mage::getSingleton('adminhtml/session')->addError('An error occurred while processing your refund request: ' . $exception->getMessage());
            Mage::throwException($exception->getMessage());
        }
        
        if ($success === false) {
            Mage::getSingleton('adminhtml/session')->addError('An error occurred while processing your refund request.');
            Mage::throwException('An error occurred while processing your refund request.');
        }
        
        return $this;
    }
    
    protected function _cancel($payment, $amount)
    {
        try {
            $cancelRequest = Mage::getModel('afterpay/request_cancel');
            
            $cancelRequest->setpayment($payment)
                          ->setOrder($payment->getOrder())
                          ->setCreditmemo($payment->getCreditmemo())
                          ->setMethod($payment->getMethod());
                          
            $invoice = $cancelRequest->loadInvoiceByTransactionId($payment->getOrder()->getAfterpayOrderReference());
            if ($invoice === false) {
                Mage::throwException($this->_getHelper()->__('Refund action is not available.'));
            }
            $cancelRequest->setInvoice($invoice);
                          
            $success = $cancelRequest->sendCancelRequest();
        } catch (Exception $exception) {
            Mage::getModel('afterpay/abstract')->logException($e);
            Mage::getSingleton('adminhtml/session')->addError('An error occurred while processing your refund request: ' . $exception->getMessage());
            Mage::throwException($exception->getMessage());
        }
        
        if ($success === false) {
            Mage::getSingleton('adminhtml/session')->addError('An error occurred while processing your refund request.');
            Mage::throwException($exception->getMessage());
        }
        
        return $this;
    }
    
    protected function _isCancel($payment)
    {
        $captureModeUsed = $payment->getOrder()->getAfterpayCaptureMode();
        $captured = $payment->getOrder()->getAfterpayCaptured();
        
        if ($captureModeUsed == 1 && !$captured) {
            return true;
        }
        
        return false;
    }
    
    protected function _canRefund($payment)
    {
        if (!$this->canRefund()) {
            Mage::throwException('Unable to refund this order.');
        }
        
        if (!Mage::getStoreConfig('afterpay/afterpay_refund/enabled', Mage::app()->getStore()->getId())) {
            Mage::throwException('Refunding is disabled.');
        }
    }
    
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        //changes the payment method code to camelCased to use with Magento's get method
        $codeString = 'get';
        $codeBits = explode('_', $this->_code);
        foreach($codeBits as $bit) {
            $codeString .= ucFirst($bit);
        }
        
        // Check if date is in timestamp information
        if(is_numeric($data->getDob()))
        {
            $timestamp = $data->getDob();
            $data->setDob(gmdate("Y-m-d\TH:i:s", $timestamp));
            $data->setYear(gmdate("Y", $timestamp));
            $data->setMonth(gmdate("m", $timestamp));
            $data->setDay(gmdate("d", $timestamp));
        }

        //OSC compatibility
        if ($data->$codeString()) {
            $data = new Varien_Object($data->$codeString());
            $infoArray = $this->_assignData($data);
        } else {
            $infoArray = $this->_assignData($data);
        }

        if(strpos($this->_code, 'portfolio_') !== false)
        {
            $info = $this->getInfoInstance();
            $info->setAdditionalInformation($infoArray);
        }
        return $this;
    }

    protected function _assignData($data)
    {
        $infoArray = array(
            'gender'      => $data->getGender(),
            'phonenumber' => $data->getPhonenumber(),
        );
        
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->_code . '/portfolio_type', Mage::app()->getStore()->getId()) == 'B2B') {
            $b2BInfoArray = array(
                'coc'         => $data->getCoc(),
                'companyname' => $data->getCompanyname(),
                'vat'         => $data->getVat(),
            );
            $infoArray = array_merge($infoArray, $b2BInfoArray);
        } else {
            $b2CInfoArray = array(
                'dob'         => $data->getDob(),
                'dob_year'    => $data->getYear(),
                'dob_month'   => $data->getMonth(),
                'dob_day'     => $data->getDay(),
                'bankaccount' => $data->getBankaccount()
            );
            $infoArray = array_merge($infoArray, $b2CInfoArray);
        }
        
        return $infoArray;
    }
}