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
 
 class Afterpay_Afterpay_Model_Soap_Authorize extends Afterpay_Afterpay_Model_Soap_Abstract
{
    protected $_isB2B = false;
    
    public function getIsB2B()
    {
        return $this->_isB2B;
    }
    
    public function setIsB2B($isB2B = false)
    {
        $this->_isB2B = $isB2B;
        return $this;
    }
    
    public function authorizationRequest()
    {
        if ($this->_isB2B) {
            $param        = $this->_addAfterPayB2BOrder();
            $paramName    = 'b2border';
            $functionName = 'validateAndCheckB2BOrder';
        } else {
            $param        = $this->_addAfterPayB2COrder();
            $paramName    = 'b2corder';
            $functionName = 'validateAndCheckB2COrder';
        }
        
        return $this->soapRequest('authorize', $functionName, $paramName, $param);
    }
    
    protected function _addAfterPayB2BOrder()
    {
        $b2bBillToAddress = $this->_addB2bBillToAddress();
        $b2bShipToAddress = $this->_addB2bShipToAddress();
        $company          = $this->_addCompany();
        $person           = $this->_addBillingPerson();
        $orderLines       = $this->_addOrderLines();
        $shopDetails      = $this->_addShopDetails();
        
        $afterPayB2BOrder = Mage::getModel('afterpay/soap_parameters_afterPayB2BOrder');
        $afterPayB2BOrder->b2bbilltoAddress           = $b2bBillToAddress;
        $afterPayB2BOrder->b2bshiptoAddress           = $b2bShipToAddress;
        $afterPayB2BOrder->company                    = $company;
        $afterPayB2BOrder->person                     = $person;
        $afterPayB2BOrder->ordernumber                = $this->_vars['orderNumber'];
        $afterPayB2BOrder->currency                   = $this->_vars['currency'];
        $afterPayB2BOrder->ipAddress                  = $this->_vars['ipAddress'];
        $afterPayB2BOrder->totalOrderAmount           = $this->_vars['totalOrderAmount'];
        $afterPayB2BOrder->orderlines                 = $orderLines;
        $afterPayB2BOrder->shopdetails                = $shopDetails;
        $afterPayB2BOrder->parentTransactionreference = false;
        
        $afterPayB2BOrder = $this->_cleanEmptyValues($afterPayB2BOrder);
        
        return $afterPayB2BOrder;
    }
    
    protected function _addAfterPayB2COrder()
    {
        $b2cBillToAddress = $this->_addB2cBillToAddress();
        $b2cShipToAddress = $this->_addB2cShipToAddress();
        $orderLines       = $this->_addOrderLines();
        $shopDetails      = $this->_addShopDetails();
        $shopper          = $this->_addShopper();
        
        $afterPayB2COrder = Mage::getModel('afterpay/soap_parameters_afterPayB2COrder');
        $afterPayB2COrder->b2cbilltoAddress           = $b2cBillToAddress;
        $afterPayB2COrder->b2cshiptoAddress           = $b2cShipToAddress;
        $afterPayB2COrder->ordernumber                = $this->_vars['orderNumber'];
        
        if(isset($this->_vars['extrafields'])) {
            $afterPayB2COrder->extrafields              = $this->_vars['extrafields'];
        } else {
            $afterPayB2COrder->extrafields              = false;
        }

        $afterPayB2COrder->bankaccountNumber          = $this->_vars['bankAccountNumber'];
        $afterPayB2COrder->currency                   = $this->_vars['currency'];
        $afterPayB2COrder->ipAddress                  = $this->_vars['ipAddress'];
        $afterPayB2COrder->totalOrderAmount           = $this->_vars['totalOrderAmount'];
        $afterPayB2COrder->orderlines                 = $orderLines;
        $afterPayB2COrder->shopdetails                = $shopDetails;
        $afterPayB2COrder->shopper                    = $shopper;
        $afterPayB2COrder->parentTransactionreference = false;
        
        $afterPayB2COrder = $this->_cleanEmptyValues($afterPayB2COrder);
        
        return $afterPayB2COrder;
    }
    
    protected function _addB2bBillToAddress()
    {
        $b2bBillToAddress = Mage::getModel('afterpay/soap_parameters_b2BAddress');
        
        $b2bBillToAddress->city                = $this->_vars['billingAddress']['city'];
        $b2bBillToAddress->housenumber         = $this->_vars['billingAddress']['houseNumber'];
        $b2bBillToAddress->housenumberAddition = $this->_vars['billingAddress']['houseNumberAddition'];
        $b2bBillToAddress->isoCountryCode      = $this->_vars['billingAddress']['isoCountryCode'];
        $b2bBillToAddress->postalcode          = $this->_vars['billingAddress']['postalCode'];
        $b2bBillToAddress->streetname          = $this->_vars['billingAddress']['streetName'];
        $b2bBillToAddress->careof              = $this->_vars['billingAddress']['careof'];
        
        $b2bBillToAddress = $this->_cleanEmptyValues($b2bBillToAddress);
        
        return $b2bBillToAddress;
    }
    
    protected function _addB2bShipToAddress()
    {
        if (is_array($this->_vars) && isset($this->_vars['isVirtual']) && $this->_vars['isVirtual'] == 1) {    
            $b2bShipToAddress = $this->_addB2bBillToAddress(); //use billing address as shipping address.
            return $b2bShipToAddress;
        }
        
        $b2bShipToAddress = Mage::getModel('afterpay/soap_parameters_b2BAddress');
        
        $b2bShipToAddress->city                = $this->_vars['shippingAddress']['city'];
        $b2bShipToAddress->housenumber         = $this->_vars['shippingAddress']['houseNumber'];
        $b2bShipToAddress->housenumberAddition = $this->_vars['shippingAddress']['houseNumberAddition'];
        $b2bShipToAddress->isoCountryCode      = $this->_vars['shippingAddress']['isoCountryCode'];
        $b2bShipToAddress->postalcode          = $this->_vars['shippingAddress']['postalCode'];
        $b2bShipToAddress->streetname          = $this->_vars['shippingAddress']['streetName'];
        $b2bShipToAddress->careof              = $this->_vars['shippingAddress']['careof'];
        
        $b2bShipToAddress = $this->_cleanEmptyValues($b2bShipToAddress);
        
        return $b2bShipToAddress;
    }
    
    protected function _addB2cBillToAddress()
    {
        $b2cBillToAddress = Mage::getModel('afterpay/soap_parameters_b2CAddress');
        
        $b2cBillToAddress->city                = $this->_vars['billingAddress']['city'];
        $b2cBillToAddress->housenumber         = $this->_vars['billingAddress']['houseNumber'];
        $b2cBillToAddress->housenumberAddition = $this->_vars['billingAddress']['houseNumberAddition'];
        $b2cBillToAddress->isoCountryCode      = $this->_vars['billingAddress']['isoCountryCode'];
        $b2cBillToAddress->postalcode          = $this->_vars['billingAddress']['postalCode'];
        $b2cBillToAddress->streetname          = $this->_vars['billingAddress']['streetName'];
        $b2cBillToAddress->referencePerson     = $this->_addBillingPerson();
        
        $b2cBillToAddress = $this->_cleanEmptyValues($b2cBillToAddress);
        
        return $b2cBillToAddress;
    }
    
    protected function _addB2cShipToAddress()
    {
        if (is_array($this->_vars) && isset($this->_vars['isVirtual']) && $this->_vars['isVirtual'] == 1) {    
            $b2cShipToAddress = $this->_addB2cBillToAddress(); //use billing address as shipping address.
            return $b2cShipToAddress;
        }
        
        $b2cShipToAddress = Mage::getModel('afterpay/soap_parameters_b2CAddress');
        
        $b2cShipToAddress->city                = $this->_vars['shippingAddress']['city'];
        $b2cShipToAddress->housenumber         = $this->_vars['shippingAddress']['houseNumber'];
        $b2cShipToAddress->housenumberAddition = $this->_vars['shippingAddress']['houseNumberAddition'];
        $b2cShipToAddress->isoCountryCode      = $this->_vars['shippingAddress']['isoCountryCode'];
        $b2cShipToAddress->postalcode          = $this->_vars['shippingAddress']['postalCode'];
        $b2cShipToAddress->streetname          = $this->_vars['shippingAddress']['streetName'];
        $b2cShipToAddress->referencePerson     = $this->_addShippingPerson();
        
        $b2cShipToAddress = $this->_cleanEmptyValues($b2cShipToAddress);
        
        return $b2cShipToAddress;
    }
    
    protected function _addShopDetails()
    {
        $shopDetails = Mage::getModel('afterpay/soap_parameters_shopDetails');
        
        $shopDetails->afterpaypluginsupplier = 'totalinternetgroup';
        $shopDetails->afterpayPluginVersion  = Mage::getConfig()->getModuleConfig("Afterpay_Afterpay")->version;
        $shopDetails->shopURL                = Mage::getBaseUrl();
        $shopDetails->webshopplatform        = 'Magento';
        
        if (method_exists('Mage', 'getEdition')) { 
            $shopDetails->webshopplatform .= ' ' . Mage::getEdition();
        }
        
        $shopDetails->webshoplatformversion  = Mage::getVersion();
        
        $shopDetails = $this->_cleanEmptyValues($shopDetails);
        
        return $shopDetails;
    }
    
    protected function _addShopper()
    {
        $shopper = Mage::getModel('afterpay/soap_parameters_shopper');
        
        $shopper->profileCreated = date('Y-m-d\TH:i:s', time());
        $shopper->finbox         = false;
        
        $shopper = $this->_cleanEmptyValues($shopper);
        
        return $shopper;
    }
    
    protected function _addBillingPerson()
    {
        $person = $this->_addPerson('billing');
        
        return $person;
    }
    
    protected function _addShippingPerson()
    {
        $person = $this->_addPerson('shipping');
        
        return $person;
    }
    
    protected function _addPerson($type = 'person')
    {
        $person = Mage::getModel('afterpay/soap_parameters_person');
        
        $person->emailaddress = $this->_vars[$type]['emailAddress'];
        $person->gender       = $this->_vars[$type]['gender'];
        $person->initials     = $this->_vars[$type]['initials'];
        $person->isoLanguage  = $this->_vars[$type]['isoLanguage'];
        $person->lastname     = $this->_vars[$type]['lastname'];
        $person->phonenumber1 = $this->_vars[$type]['phonenumber'];
        $person->dateofbirth  = $this->_vars[$type]['dob'];
        
        $person = $this->_cleanEmptyValues($person);
        
        return $person;
    }
    
    protected function _addOrderLines()
    {
        $orderLines = array();
        foreach ($this->_vars['orderLines'] as $line) {
            if (empty($line)) {
                continue;
            }
            
            $orderLine = Mage::getModel('afterpay/soap_parameters_orderLine');
            
            $orderLine->articleDescription = preg_replace("/[^a-zA-Z0-9\_\-\s]/i", "", $line['articleDescription']);
            $orderLine->articleId          = $line['articleId'];
            $orderLine->quantity           = $line['quantity'];
            $orderLine->unitprice          = $line['unitPrice'];
            $orderLine->vatcategory        = $line['vatCategory'];
            
            $orderLine = $this->_cleanEmptyValues($orderLine);
            
            $orderLines[] = $orderLine;
        }
        
        $orderLines = $this->_cleanEmptyValues($orderLines);
        
        return $orderLines;
    }
    
    protected function _addCompany()
    {
        $company = Mage::getModel('afterpay/soap_parameters_company');
        
        $company->cocnumber   = $this->_vars['company']['cocNumber'];
        $company->companyname = $this->_vars['company']['companyName'];
        $company->department  = '';
        $company->vatnumber   = '';
        
        $company = $this->_cleanEmptyValues($company);
        
        return $company;
    }
}