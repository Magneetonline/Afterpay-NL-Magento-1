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
 
 class Afterpay_Afterpay_Model_Abstract extends Mage_Payment_Model_Method_Abstract
{    
    const AFTERPAY_SUCCESS           = 'AFTERPAY_SUCCESS';
    const AFTERPAY_FAILED            = 'AFTERPAY_FAILED';
    const AFTERPAY_ERROR             = 'AFTERPAY_ERROR';
    const AFTERPAY_PENDING_PAYMENT   = 'AFTERPAY_PENDING_PAYMENT';
    const AFTERPAY_ACCEPTED          = 'AFTERPAY_ACCEPTED';
    const AFTERPAY_REJECTED          = 'AFTERPAY_REJECTED';
    const AFTERPAY_PENDING           = 'AFTERPAY_PENDING';
    const AFTERPAY_VALIDATION_ERROR  = 'AFTERPAY_VALIDATION_ERROR';
    
    protected $_helper = '';
    protected $_order = '';
    protected $_debugEmail = '';
    protected $_billingInfo = '';
    protected $_shippingInfo = '';
    protected $_session = '';
    
    public $responseCodes = array(
        '0' => self::AFTERPAY_SUCCESS,
        '1' => self::AFTERPAY_ERROR,
        '2' => self::AFTERPAY_VALIDATION_ERROR,
        '3' => self::AFTERPAY_REJECTED,
        '4' => self::AFTERPAY_PENDING_PAYMENT,
    
        'A' => self::AFTERPAY_ACCEPTED,
        'W' => self::AFTERPAY_REJECTED,
        'P' => self::AFTERPAY_PENDING,
    );
    
    /**
     * Retrieves instance of the last used order
     */
    protected function _loadLastOrder()
    {
        if (!empty($this->_order)) {
            return;
        }
        
        $session = Mage::getSingleton('checkout/session');
        $orderId = $session->getLastRealOrderId();
        if (!empty($orderId)) {
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        }
    }
    
    public function setHelper($helper) {
        $this->_helper = $helper;
        return $this;
    }
    
    public function getHelper()
    {
        return $this->_helper;
    }
    
    public function setOrder($order) {
        $this->_order = $order;
        return $this;
    }
    
    public function getOrder()
    {
        return $this->_order;
    }
    
    public function setLastOrder($order)
    {
        $this->_order = $order;
        return $this;
    }
    
    public function getLastOrder()
    {
        return $this->_order;
    }
    
    public function setDebugEmail($debugEmail)
    {
        $this->_debugEmail = $debugEmail;
        return $this;
    }
    
    public function getDebugEmail()
    {
        return $this->_debugEmail;
    }
    
    public function setBillingInfo($billingInfo)
    {
        $this->_billingInfo = $billingInfo;
        return $this;
    }
    
    public function getBillingInfo()
    {
        return $this->_billingInfo;
    }
    
    public function setShippingInfo($shippingInfo)
    {
        $this->_shippingInfo = $shippingInfo;
        return $this;
    }
    
    public function getShippingInfo()
    {
        return $this->_shippingInfo;
    }    
    
    public function setSession($session)
    {
        $this->_session = $session;
        return $this;
    }
    
    public function getSession()
    {
        return $this->_session;
    }
    
    public function __construct()
    {
        return Varien_Object::__construct(func_get_args());
    }
    
    protected function _construct()
    {       
        $this->setHelper(Mage::helper('afterpay'));
        $this->_loadLastOrder();
        $this->setSession(Mage::getSingleton('core/session'));
        $this->_setOrderBillingInfo();
        $this->_setOrderShippingInfo();

        $this->_checkExpired();
    }
    
    /**
     * Checks if the order object is still there. Prevents errors when session has expired.
     */
    protected function _checkExpired()
    {
        return true; //need to fix this check later
//        if (empty($this->_order)) {
//            $returnUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) 
//                . (Mage::getStoreConfig('web/seo/use_rewrites', Mage::app()->getStore()->getStoreId()) != 1 ? 'index.php/':'') 
//                . (Mage::getStoreConfig('web/url/use_store', Mage::app()->getStore()->getStoreId()) != 1 ? '' : Mage::app()->getStore()->getCode() . '/')
//                . Mage::getStoreConfig('afterpay/afterpay/failure_redirect', Mage::app()->getStore()->getStoreId());
//            
//            header('location:' . $returnUrl);
//        }
    }
    
    public function setOrderBillingInfo()
    {
        return $this->_setOrderBillingInfo();
    }
    
    /**
     * retrieve billing information from order
     * 
     */
    protected function _setOrderBillingInfo()
    {
        if (empty($this->_order)) {
            return $this;
        }
        $billingAddress = $this->_order->getBillingAddress();
                
        $billingInfo = array(
            'firstname'     => $billingAddress->getFirstname(),
            'lastname'        => $billingAddress->getLastname(),
            'city'             => $billingAddress->getCity(),
            'state'         => $billingAddress->getState(),
            'address'         => $billingAddress->getStreetFull(),
            'zip'             => $billingAddress->getPostcode(),
            'email'         => $this->_order->getCustomerEmail(),
            'telephone'     => $billingAddress->getTelephone(),
            'fax'             => $billingAddress->getFax(),
            'countryCode'     => $billingAddress->getCountry()
        );
        
        return $this->setBillingInfo($billingInfo);
    }
    
    public function setOrderShippingInfo()
    {
        return $this->_setOrderShippingInfo();
    }
    
    /**
     * retrieve shipping information from order
     * 
     */
    protected function _setOrderShippingInfo()
    {
        if (empty($this->_order)) {
            return $this;
        }
                
        $shippingAddress = $this->_order->getShippingAddress();
        
        if (!$shippingAddress) {
            return $this;
        }

        $method = strtolower($this->_order->getShippingMethod());
    
        $firstname = $shippingAddress->getFirstname();
        $lastname = $shippingAddress->getLastname();
        $city = $shippingAddress->getCity();
        $state = $shippingAddress->getState();
        $address = $shippingAddress->getStreetFull();
        $zip = $shippingAddress->getPostcode();
        $email = $this->_order->getCustomerEmail();
        $telephone = $shippingAddress->getTelephone();
        $fax = $shippingAddress->getFax();
        $countrycode = $shippingAddress->getCountry();

        // COMPATIBLE WITH PAAZL
        if (substr($method, 0, 16) == 'paazl_pakjegemak') {
            $rate = Mage::getModel('sales/quote_address_rate')->load($this->_order->getShippingMethod(), 'code');
            $street = explode(" ", $rate->getServicePointAddress());
            $firstname = 'P';
            $lastname = 'Paazl Pakjegemak';

            if (count($street) > 0) {
                $street_last = $street[count($street)-1];
                $street_name = str_replace($street[count($street)-1], '', $rate->getServicePointAddress());
                $street_name = str_replace($street[count($street)-2], '', $street_name);
                $street_add = $street_last;
                $street_number = $street[count($street)-2];  
                $street_name = preg_replace("/[\n\r]/","|",$street_name);
                $address = $street_name . ' ' . $street_number . ' ' . $street_add;
            }
        }
        
        // COMPATIBLE WITH POSTNL PAKJEGEMAK
        if (Mage::helper('core')->isModuleEnabled('TIG_PostNL'))
        {
            $addresses = $this->_order->getAddressesCollection();

            foreach ($addresses as $addressNew) {
                if ($addressNew->getAddressType() == 'pakje_gemak' ) {
                    $firstname = 'A';
                    $lastname = 'POSTNL afhaalpunt ' . $addressNew->getCompany();
                    $zip = $addressNew->getPostcode();
                    $telephone = $addressNew->getTelephone();
                    $countrycode = $addressNew->getCountryId();
                    $street = $addressNew->getStreet();
                    if(count($street) > 1) {
                         $address = $street[0] . ' ' . $street[1];
                    } else {
                         $address = $street[0];
                    }
                }
            }
        }
        
        // COMPATIBLE WITH MYPARCEL PAKJEGEMAK
        if (Mage::helper('core')->isModuleEnabled('TIG_MyParcel2014'))
        {
            // Myparcel saves the pickup location only on the quote address collection
            $quote = Mage::getModel('sales/quote')->load($this->_order->getQuoteId());
            $addresses = $quote->getAddressesCollection();

            foreach ($addresses as $addressNew) {
                if ($addressNew->getAddressType() == 'pakje_gemak' ) {
                    $firstname = 'A';
                    $lastname = 'POSTNL Afhaalpunt ' . $addressNew->getCompany();
                    $zip = $addressNew->getPostcode();
                    $telephone = $addressNew->getTelephone();
                    $countrycode = $addressNew->getCountryId();
                    $street = $addressNew->getStreet();
                    if(count($street) > 1) {
                         $address = $street[0] . ' ' . $street[1];
                    } else {
                         $address = $street[0];
                    }
                }
            }
        }

        $shippingInfo = array(
            'firstname'     => $firstname,
            'lastname'    => $lastname,
            'city'     => $city,
            'state'     => $state,
            'address'    => $address,
            'zip'     => $zip,
            'email'     => $email,
            'telephone'     => $telephone,
            'fax'     => $fax,
            'countryCode' => $countrycode
        );

        return $this->setShippingInfo($shippingInfo);
    }
    
    /**
     * Restores a previously closed quote so that the cart stays filled after an unsuccessfull order
     */
    public function restoreQuote()
    {
        $quoteId = $this->_order->getQuoteId();
        $quote = Mage::getModel('sales/quote')->load($quoteId)->setIsActive(true)->save();
        
        Mage::getSingleton('checkout/session')->replaceQuote($quote);
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(true)->save();
        Mage::getSingleton('checkout/session')->getQuote()->setReservedOrderId(null)->save();
    }
    
    public function _cleanArrayForSoap($array)
    {
        $cleanArray = array();
        
        foreach ($array as $key => $value) {
            $value = str_replace('\r', ' ', $value);
            $value = str_replace('\n', ' ', $value);
            $cleanArray[$key] = $value;
        }
        
        return $cleanArray;
    }
    
    public function log($message, $force = false)
    {
        $this->_helper->log($message, $force);
    }

    public function logException($e)
    {
        $this->_helper->log($e);
    }
    
    public function sendDebugEmail()
    {
        $debugEmailConfig = Mage::getStoreConfig('afterpay/afterpay_general/debug_mode', Mage::app()->getStore()->getStoreId());
        if ($debugEmailConfig == 'no') {
            return false;
        } elseif ($debugEmailConfig == 'log') {
            return $this->log($this->_debugEmail, true);
        } else {
            return $this->_helper->sendDebugEmail($this->getDebugEmail());
        }
    }
}