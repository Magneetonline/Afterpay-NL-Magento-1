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
 
class Afterpay_Afterpay_Model_Request_Abstract extends Afterpay_Afterpay_Model_Abstract
{
    protected $_vars;
    protected $_method;
    protected $_testMode = false;
    protected $_additionalFields = array();
    protected $_isB2B = false;
    protected $_country;

    public function getVars()
    {
        return $this->_vars;
    }

    public function setVars($vars = array())
    {
        $this->_vars = $vars;
        return $this;
    }

    public function getMethod()
    {
        return $this->_method;
    }

    public function setMethod($method = '')
    {
        $this->_method = $method;
        return $this;
    }

    public function getTestMode()
    {
        return $this->_testMode;
    }

    public function setTestMode($mode = false)
    {
        $this->_testMode = $mode;
        return $this;
    }
    
    public function getCountry()
    {
        return $this->_country;
    }

    public function setCountry($country = 'nlnl')
    {
        $this->_country = $country;
        return $this;
    }    

    public function getAdditionalFields()
    {
        return $this->_additionalFields;
    }

    public function setAdditionalFields($fields = false)
    {
        $this->_additionalFields = $fields;
        return $this;
    }
    
    public function getIsB2B()
    {
        return $this->_isB2B;
    }
    
    public function setIsB2B($isB2B = false)
    {
        $this->_isB2B = $isB2B;
        return $this;
    }

    protected function _construct() {
        parent::_construct();

        $method = $this->_order->getPayment()->getMethod();
        $this->setMethod($method);
        
        $info = $this->_order->getPayment()->getMethodInstance()->getInfoInstance();
        $testMode = (bool) Mage::getStoreConfig('afterpay/afterpay_' . $method . '/mode', $this->_order->getStoreId());
                
        $country = (string) Mage::getStoreConfig('afterpay/afterpay_' . $this->_method . '/portfolio_country', $this->_order->getStoreId());
        $this->setCountry($country);
                
        $this->setAdditionalFields($info->getAdditionalInformation());
        $this->setTestMode($testMode);
        
        $this->_addIsVirtual();
        
        $portfolioType = Mage::getStoreConfig('afterpay/afterpay_' . $this->_method . '/portfolio_type', $this->_order->getStoreId());
        if ($portfolioType == 'B2B') {
            $this->setIsB2B(true);
        }
    }

    public function sendRequest()
    {
        $this->_debugEmail .= 'Chosen portfolio: ' . $this->_method . "\n";
        
        $this->_storeCaptureMode();

        $responseModel = Mage::getModel('afterpay/response_abstract');
        
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
                $this->restoreQuote();
                return false;
            }
        }

        //hack to prevent SQL errors when using onestepcheckout
        Mage::getSingleton('checkout/session')->getQuote()->setReservedOrderId(null)->save();
        
        try {
            $this->buildRequest();
        } catch (Exception $exception) {
            $this->sendDebugEmail();
            $this->logException($exception);
            $this->restoreQuote();
            Mage::getSingleton('core/session')->addError(
                Mage::helper('afterpay')->__($exception->getMessage())
            );
            
            return false;
        }
        
        $this->_debugEmail .= "Building SOAP request... \n";
        //send the transaction request using SOAP
        $soap = Mage::getModel('afterpay/soap_authorize');
        $soap->setVars($this->getVars())
             ->setMethod($this->getMethod())
             ->setTestMode($this->getTestMode())
             ->setIsB2B($this->getIsB2B())
             ->setCountry($this->getCountry());
             
        list($response, $responseXML, $requestXML) = $soap->authorizationRequest();

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
                      ->setDebugEmail($this->_debugEmail)
                      ->setRequest($this);
                
        try {
            return $responseModel->processResponse();
        } catch (Exception $exception) {
            $responseModel->sendDebugEmail();
            $this->logException($exception);
            $this->restoreQuote();
            return false;
        }
    }
    
    public function buildRequest()
    {
        $this->_debugEmail .= "\n";
        //forms an array with all payment-independant variables (such as merchantkey, order id etc.) which are required for the transaction request
        $this->_addOrderVariables();
        $this->_addShopVariables();
        $this->_addPortfolioVariables();
        $this->_addCustomerVariables();
        $this->_addBillingAddressVariables();
        $this->_addShippingAddressVariables();
        if ($this->_isB2B) {
            $this->_addB2BVariables();
        } else {
            $this->_addB2CVariables();
        }
        
        $this->_debugEmail .= "Firing request events. \n";
        //event that allows individual payment methods to add additional variables such as bankaccount number
        //currently this is not used, however developers may use this event to easily modify the values sent to AfterPay
        Mage::dispatchEvent('afterpay_request_addcustomvars', array('request' => $this, 'order' => $this->_order));

        $this->_debugEmail .= "Events fired!. \n";

        //clean the array for a soap request
        $this->setVars($this->_cleanArrayForSoap($this->getVars()));

        $this->_debugEmail .= "Variable array:" . var_export($this->_vars, true) . "\n\n";
        
    }

    protected function _addShopVariables()
    {
        if ($this->_testMode) { 
            $merchantId = Mage::getStoreConfig('afterpay/afterpay_' . $this->_method . '/test_merchant_id', $this->_order->getStoreId());
        } else {
            $merchantId = Mage::getStoreConfig('afterpay/afterpay_' . $this->_method . '/live_merchant_id', $this->_order->getStoreId()); 
        }
        
        $array = array(
            'merchantId' => $merchantId,
        );
        
        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }

        $this->_debugEmail .= "Shop variables added! \n";
    }

    protected function _addPortfolioVariables()
    {
        list($portfolioId, $password) = $this->_getPortfolioId();
        
        $array = array(
            'portfolioId' => $portfolioId,
            'password'    => $password,
        );
        
        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }
        
        $this->_debugEmail .= "Portfolio variables added! \n";
    }
    
    protected function _addOrderVariables($refund = false)
    {
        $orderLines = $this->_getOrderLines();
        

        $advisoryproces = Mage::getStoreConfig('afterpay/afterpay_' . $this->_method . '/advisoryprocess', $this->_order->getStoreId());
        
        if($advisoryproces == 1) {
            $extrafields = array('nameField'=>'paymentPlan', 'valueField'=>'True');
        }
        
        $totalOrderAmount = round($this->_order->getBaseGrandTotal() * 100, 0);
        
        if ($refund == false) {
            $orderLines = $this->_checkRoundingError($totalOrderAmount, $orderLines);
        }
        
        if($advisoryproces == 1) {
            $array = array(
                'currency'         => 'EUR',
                'orderNumber'      => $this->_order->getIncrementId(),
                'extrafields'       => $extrafields,
                'totalOrderAmount' => (int) $totalOrderAmount,
                'orderLines'       => $orderLines,
            );
        } else {
            $array = array(
                'currency'         => 'EUR',
                'orderNumber'      => $this->_order->getIncrementId(),
                'totalOrderAmount' => (int) $totalOrderAmount,
                'orderLines'       => $orderLines,
            );
        }
        
        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }

        $this->_debugEmail .= "Order variables added! \n";
    }
    
    protected function _checkRoundingError($totalOrderAmount, $orderlines)
    {
        $orderlinesTotal = 0;
        
        foreach($orderlines as $value)
        {
            $orderlinesTotal = $orderlinesTotal + $value['unitPrice'];
        }
        
        $orderDiff = $totalOrderAmount - $orderlinesTotal;
        
        if ($orderDiff <> 0)
        {
            $orderlines[] = array(
               'articleDescription' => 'Correctie',
               'articleId'          => '1',
               'unitPrice'          => $orderDiff,
               'vatCategory'        => 1,
               'quantity'           => 1,
            );
        }
        
        return $orderlines;
    }    
    
    protected function _addCustomerVariables()
    {
        $array = array(
            'ipAddress'         => $_SERVER['REMOTE_ADDR'],
        );
        
        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }
        
        $this->_debugEmail .= "Customer variables added! \n";
    }
    
    protected function _addBillingAddressVariables()
    {
        $streetParts = $this->_splitStreet($this->_billingInfo['address']);
        
        $array = array(
            'billingAddress' => array(
                'city'                => $this->_billingInfo['city'],
                'houseNumber'         => $streetParts['houseNumber'],
                'houseNumberAddition' => $streetParts['houseNumberAddition'],
                'isoCountryCode'      => $this->_billingInfo['countryCode'],
                'postalCode'          => $this->_billingInfo['zip'],
                'streetName'          => $streetParts['streetName'],
            ),
        );
        
        $this->_addPersonVariables('billing');
        
        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }
        
        $this->_debugEmail .= "Billing address variables added!. \n";
    }
    
    /*
     * Set parameter when order contains only virtual products
     */ 
    protected function _addIsVirtual()
    {
        if ($this->_order->getIsVirtual() == '1') {
            $array = array('isVirtual' => 1);
            
            if (is_array($this->_vars)) {
                $this->_vars = array_merge($this->_vars, $array);
            } else {
                $this->_vars = $array;
            }
        }    
    }
    
    protected function _addShippingAddressVariables()
    {        
        if (is_array($this->_vars) && isset($this->_vars['isVirtual']) && $this->_vars['isVirtual'] == 1) {
            $this->_debugEmail .= "Virtual order: Shipping address = Billing address \n";
            return;
        }

        $streetParts = $this->_splitStreet($this->_shippingInfo['address']);
        
        $array = array(
            'shippingAddress' => array(
                'city'                => $this->_shippingInfo['city'],
                'houseNumber'         => $streetParts['houseNumber'],
                'houseNumberAddition' => $streetParts['houseNumberAddition'],
                'isoCountryCode'      => $this->_shippingInfo['countryCode'],
                'postalCode'          => $this->_shippingInfo['zip'],
                'streetName'          => $streetParts['streetName'],
            ),
        );
        
        $this->_addPersonVariables('shipping');
        
        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }
        
        $this->_debugEmail .= "Shipping address variables added! \n";
    }

    protected function _addB2BVariables()
    {
        $array = array(
            'billingAddress' => array(
                'careof' => 'test',
            ),
            'shippingAddress' => array(
                'careof' => 'test',
            ),
        );
        $companyArray = array(
            'company' => array(
                'cocNumber'   => $this->_additionalFields['coc'],
                'companyName' => $this->_additionalFields['companyname']
            ),
        );
        
        if (is_array($this->_vars)) {
            $this->_vars = array_merge_recursive($this->_vars, $array);
            $this->_vars = array_merge($this->_vars, $companyArray);
        } else {
            $this->_vars = $array;
        }
        
        $this->_debugEmail .= "Shipping address variables added! \n";
    }

    protected function _addB2CVariables()
    {
        // Check if variable bankaccount is available
        if(isset($this->_additionalFields['bankaccount']))
        {
            // Strip whitespace from bankaccount string
            $bankAccountNumber = preg_replace( '/\s+/' , '' , $this->_additionalFields['bankaccount'] );
            $array = array(
                'bankAccountNumber' => $bankAccountNumber,
            );
        }
        else
        {
            $array = array(
                'bankAccountNumber' => '',
            );
        }
        
        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }
        
        $this->_debugEmail .= "Shipping address variables added! \n";
    }
    
    protected function _addPersonVariables($type = 'person')
    {
        switch($type) {
            
            case 'shipping':   $email       = $this->_shippingInfo['email'];
                               $initials    = $this->_getInitials('shipping');
                               $phoneNumber = $this->_getPhoneNumber('shipping');
                               $lastname    = ucfirst($this->_shippingInfo['lastname']);
                               break;
            case 'billing':
            default:           $email       = $this->_billingInfo['email'];
                               $initials    = $this->_getInitials('billing');
                               $phoneNumber = $this->_getPhoneNumber('billing');
                               $lastname    = ucfirst($this->_billingInfo['lastname']);
                               break;
        }
        
        $array = array(
            $type => array(
                'emailAddress'  => $email,
                'gender'        => $this->_getGender(),
                'initials'      => $initials,
                'isoLanguage'   => $this->_getIsoLanguage(),
                'lastname'      => $lastname,
                'phonenumber'   => $phoneNumber,
                'dob'           => ($this->_isB2B) ? '1970-01-01T00:00:00' : $this->_getDob()
            ),
        );
        
        if (is_array($this->_vars)) {
            $this->_vars = array_merge($this->_vars, $array);
        } else {
            $this->_vars = $array;
        }
        
        $this->_debugEmail .= "{$type} person variables added! \n";
    }
    
    protected function _getOrderLines()
    {
        $orderLines = array();

        foreach ($this->_order->getAllItems() as $orderItem) 
        {
            
            if (empty($orderItem) || $orderItem->hasParentItemId()) {
                continue;
            }
            
            // Magento 1.6 does not have the function getProduct, to prevent errors load product on id
            if (!is_object($orderItem->getProduct())) {
                $product = Mage::getModel('catalog/product')->load($orderItem->getProductId());
            } else {
                $product = $orderItem->getProduct();
            }
          
            $vatCategory = $this->_getTaxCategory($orderItem->getProduct()->getTaxClassId());

            // Determine specific settings for bundled products
            if($orderItem->getProductType() == 'bundle') {
                $bundled = $product;
                
                // Check if price is dynamic or fixed 0 = dynamic and must show orderlines, 1 = fixed and only 1 orderline is shown
                if($bundled->getPriceType() == 0) {
                
                    $bundleitems = $orderItem->getProductOptions();
                    $i = 1;
                    // logic for bundles with dynamic pricing and for that specified orderlines
                    $selectionCollection = $bundled->getTypeInstance(true)->getSelectionsCollection(
                    $bundled->getTypeInstance(true)->getOptionsIds($bundled), 
                        $bundled
                    );
                    
                    // First get vat category, information is not available in bundle_options. But qty information and unit price is not in $selectionCollection
                    foreach($selectionCollection as $option)
                    {
                        $bundleitems['bundle_options'][$i]['value'][0]['vatcategory'] = $this->_getTaxCategory($option->getTaxClassId());
                        $i++;
                    }

                    foreach($bundleitems['bundle_options'] as $item)
                    {
                        if(isset($item['value'])) {
                            foreach($item['value'] as $subitem) {

                                if(isset($subitem['price']) && floatval($subitem['price']) > 0)
                                {
                                    $line = array(
                                        'articleDescription' => $subitem['qty'] . ' x ' . $orderItem->getName() . ': '. $subitem['title'],
                                        'articleId'          => $orderItem->getSku(),
                                        'unitPrice'          => round($subitem['qty'] * $subitem['price'] * 100,0),
                                        'vatCategory'        => 1,
                                        'quantity'           => 1
                                    );
                                    $orderLines[] = $line;
                                }
                            }
                        }
                    }

                    continue;
                    
                }
            }

            // Changed calculation from unitPrice to orderLinePrice due to impossible to recalculate unitprice,
            // because of differences in outcome between TAX settings: Unit, OrderLine and Total.
            // Quantity will always be 1 and quantity ordered will be in the article description.
                
            // Calculate the discount per row item and deduct this from the row total. Only if separate discount is not enabled
            if(!Mage::getStoreConfig('afterpay/afterpay_general/separate_discount', $this->_order->getStoreId())) {
                if(Mage::getStoreConfig('tax/calculation/price_includes_tax', $this->_order->getStoreId())) {
                    $discountInclTax = $orderItem->getDiscountAmount(); // * (1 + ($orderItem->getTaxPercent() / 100));
                } else {
                    $discountInclTax = $orderItem->getDiscountAmount() * (1 + ($orderItem->getTaxPercent() / 100));
                }
                $orderLinePrice = round(($orderItem->getRowTotalInclTax() - $discountInclTax) * 100,0);
            } else {
                $orderLinePrice = round($orderItem->getRowTotalInclTax() * 100,0);
            }
    
            $line = array(
               'articleDescription' => (int) $orderItem->getQtyOrdered() . ' x '. $orderItem->getName(),
               'articleId'          => $orderItem->getSku(),
               'unitPrice'          => $orderLinePrice,
               'vatCategory'        => $vatCategory,
               'quantity'           => 1,
            );
                
            $orderLines[] = $line;
        }
            
        $orderLines[] = $this->_addShippingLine();
        
        // If separate discount is enabled create a separate order line with total discount
        if(Mage::getStoreConfig('afterpay/afterpay_general/separate_discount', $this->_order->getStoreId())) {
            $orderLines[] = $this->_addDiscountLine();
        } else {
            $discount = (float) $this->_order->getBaseDiscountAmount();
            
            if (!empty($discount)) {
            
                $line = array(
                    'articleDescription' => 'De stuksprijs is incl. eventuele korting',
                    'articleId'          => 'KORTING',
                    'unitPrice'          => 0,
                    'vatCategory'        => 4,
                    'quantity'           => 1
                );
                
                $orderLines[] = $line;
            }
        }
            
        $orderLines[] = $this->_addPaymentFeeLine();
        $orderLines[] = $this->_addGiftWrapLine();
        $orderLines[] = $this->_addGiftWrapSeparateItemsLine();
        $orderLines[] = $this->_addGiftWrapPrintedCardLine();
        $orderLines[] = $this->_addGiftCardLine();
        $orderLines[] = $this->_addStoreCreditsLine();
        $orderLines[] = $this->_addRewardPointsLine();
    
        return $orderLines;
    }
    
    protected function _addShippingLine()
    {
        $shipping  = (float) $this->_order->getBaseShippingAmount();
        if (!empty($shipping)) {
            $shippingLine = array(
                'articleDescription' => 'Verzendkosten',
                'articleId'          => 'VERZ',
                'unitPrice'          => round(($shipping + $this->_order->getBaseShippingTaxAmount()) * 100, 0),
                'vatCategory'        => $this->_getTaxCategory(Mage::getStoreConfig('tax/classes/shipping_tax_class', $this->_order->getStoreId())),
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
            $paymentFee = (float) $this->_order->getAfterpayfeeAmount();
            
            if (!empty($paymentFee)) {
                $paymentFeeLine = array(
                    'articleDescription' => Mage::getStoreConfig('afterpay/afterpay_afterpayfee/afterpayfee_label', $this->_order->getStoreId()),
                    'articleId'          => 'FEE',
                    'unitPrice'          => round($paymentFee * 100, 0),
                    'vatCategory'        => 1,
                    'quantity'           => 1,
                );
            
                return $paymentFeeLine;
            }
        }
        
        // Check if Fooman Surcharge is used for service fee
        if (Mage::helper('core')->isModuleEnabled('Fooman_Surcharge'))
        {
            $paymentFee = $this->_order->getFoomanSurchargeAmount() + $this->_order->getFoomanSurchargeTaxAmount();
            
            if (!empty($paymentFee)) {
                $paymentFeeLine = array(
                    'articleDescription' => $this->_order->getFoomanSurchargeDescription(),
                    'articleId'          => 'FEE',
                    'unitPrice'          => round($paymentFee * 100, 0),
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
                    'unitPrice'          => round($paymentFee * 100, 0),
                    'vatCategory'        => 1,
                    'quantity'           => 1,
                );
            
                return $paymentFeeLine;
            }
        }
        
        return false;
    }

    protected function _addGiftWrapLine()
    {        
        $giftWrap = (float) $this->_order->getGwBasePrice();
        if (!empty($giftWrap)) {
            $giftWrapLine = array(
                'articleDescription' => 'Cadeauverpakking voor bestelling',
                'articleId'          => 'WRAP',
                'unitPrice'          => round(($giftWrap + $this->_order->getGwBaseTaxAmount()) * 100, 0),
                'vatCategory'        => $this->_getTaxCategory(Mage::getStoreConfig('tax/classes/wrapping_tax_class', $this->_order->getStoreId())),
                'quantity'           => 1,
            );
            
            return $giftWrapLine;
        }
        return false;
    }
    
    protected function _addGiftWrapSeparateItemsLine()
    {        
        $giftWrapItems = (float) $this->_order->getGwItemsBasePrice();
        if (!empty($giftWrapItems)) {
            $giftWrapItemLine = array(
                'articleDescription' => 'Cadeauverpakking voor artikelen',
                'articleId'          => 'WRAPITEMS',
                'unitPrice'          => round(($giftWrapItems + $this->_order->getGwItemsBaseTaxAmount()) * 100, 0),
                'vatCategory'        => $this->_getTaxCategory(Mage::getStoreConfig('tax/classes/wrapping_tax_class', $this->_order->getStoreId())),
                'quantity'           => 1,
            );
            
            return $giftWrapItemLine;
        }
        return false;
    }
    
    protected function _addGiftWrapPrintedCardLine()
    {       
        $giftWrapPrintedCard = (float) $this->_order->getGwPrintedCardBasePrice();
        if (!empty($giftWrapPrintedCard)) {
            $giftWrapPrintedCardLine = array(
                'articleDescription' => 'Gedrukte kaart',
                'articleId'          => 'PRINTCARD',
                'unitPrice'          => round(($giftWrapPrintedCard + $this->_order->getGwPrintedCardBaseTaxAmount()) * 100, 0),
                'vatCategory'        => $this->_getTaxCategory(Mage::getStoreConfig('tax/classes/wrapping_tax_class', $this->_order->getStoreId())),
                'quantity'           => 1,
            );
            
            return $giftWrapPrintedCardLine;
        }
        return false;
    }
    
    protected function _addGiftCardLine()
    {
        $giftCard = (float) $this->_order->getBaseGiftCardsAmount();
        if (!empty($giftCard)) {
            $giftCardLine = array(
                'articleDescription' => 'Cadeaubon',
                'articleId'          => 'KADO',
                'unitPrice'          => round(($giftCard * -1.00) * 100, 0), //negative value
                'vatCategory'        => 4,
                'quantity'           => 1,
            );
            
            return $giftCardLine;
        }
        return false;
    }

    protected function _addRewardPointsLine()
    {
        $rewardPoints = (float) $this->_order->getBaseRewardCurrencyAmount();
        if (!empty($rewardPoints)) {
            $rewardPointsLine = array(
                'articleDescription' => 'Reward Points',
                'articleId'          => 'REWARD',
                'unitPrice'          => round(($rewardPoints * -1.00) * 100, 0), //negative value
                'vatCategory'        => 4,
                'quantity'           => 1,
            );
            
            return $rewardPointsLine;
        }
        return false;
    }

    protected function _addStoreCreditsLine()
    {
        // Check if there are Magento Enterprise Store Credits being used
        $storeCredits = (float) $this->_order->getBaseCustomerBalanceAmount();

        // Check if there are Aheadworks Store Credits used
        if (Mage::helper('core')->isModuleEnabled('AW_Storecredit') && is_array($this->_order->getAwStorecredit()))
        {
            $storeCredits = 0;
            foreach($this->_order->getAwStorecredit() as $awStoreCredit)
            {
                $storeCredits += (float) $awStoreCredit->getStorecreditAmount();
            }
        }

        if (!empty($storeCredits)) {
            $storeCreditsLine = array(
                'articleDescription' => 'Store Credits',
                'articleId'          => 'STORCRED',
                'unitPrice'          => round(($storeCredits * -1.00) * 100, 0), //negative value
                'vatCategory'        => 4,
                'quantity'           => 1,
            );
            
            return $storeCreditsLine;
        }
        return false;
    }
    
    protected function _addDiscountLine()
    {
        $vatCategory = $this->_getTaxCategory(Mage::getStoreConfig('afterpay/afterpay_tax/discount_tax_class', $this->_order->getStoreId()));
        $taxCalculationOnDiscount = Mage::getStoreConfig('tax/calculation/discount_tax', $this->_order->getStoreId());
        $discount = (float) $this->_order->getBaseDiscountAmount();
        
        // If the calculation of the discount is excluding tax and the configuration of AfterPay is including tax then calculate the amount including tax
        if ($taxCalculationOnDiscount == 0 && $vatCategory == 1) {
            $discount = ($discount * 121) / 100;
        }
        
        if (!empty($discount)) {
            $discountLine = array(
                'articleDescription' => 'Korting',
                'articleId'          => 'DISCOUNT',
                'unitPrice'          => round($discount * 100, 0),
                'vatCategory'        => $vatCategory,
                'quantity'           => 1,
            );
            
            return $discountLine;
        }
        
        return false;
    }
    
    protected function _getTaxCategory($taxClassId)
    {
        if (!$taxClassId) {
            return 4;
        }
        
        $highTaxClasses = explode(',', Mage::getStoreConfig('afterpay/afterpay_tax/high', $this->_order->getStoreId()));
        $lowTaxClasses  = explode(',', Mage::getStoreConfig('afterpay/afterpay_tax/low', $this->_order->getStoreId()));
        $zeroTaxClasses = explode(',', Mage::getStoreConfig('afterpay/afterpay_tax/zero', $this->_order->getStoreId()));
        $noTaxClasses   = explode(',', Mage::getStoreConfig('afterpay/afterpay_tax/no', $this->_order->getStoreId()));
        
        if (in_array($taxClassId, $highTaxClasses)) {
            return 1;
        } elseif (in_array($taxClassId, $lowTaxClasses)) {
            return 2;
        } elseif (in_array($taxClassId, $zeroTaxClasses)) {
            return 3;
        } elseif (in_array($taxClassId, $noTaxClasses)) {
            return 4;
        } else {
            Mage::throwException($this->_helper->__('Did not recognize tax class for class ID: ') . $taxClassId);
        }
    }
    
    protected function _getPortfolioId()
    {
        $portfolioId = Mage::getStoreConfig("afterpay/afterpay_{$this->_method}/portfolio_id", $this->_order->getStoreId());
        
        if (!$this->_testMode) {
            $password = Mage::getStoreConfig('afterpay/afterpay_' . $this->_method . '/live_password', $this->_order->getStoreId());
        } else {
            $password = Mage::getStoreConfig('afterpay/afterpay_' . $this->_method . '/test_password', $this->_order->getStoreId());
        }
        
        return array($portfolioId, $password);
    }
    
    protected function _getInitials($type)
    {
        if ($type == 'shipping') {
            $firstName = $this->_shippingInfo['firstname'];
        } else {
            $firstName = $this->_billingInfo['firstname'];
        }
        
        if ($this->getCountry() == 'dede') {
            return $firstName;
        }
        
        $names = explode(' ', $firstName);
        
        $initials = '';
        foreach ($names as $name) {
            $ucName = ucfirst($name);
            $initials .= $ucName[0] . '.';
        }
        
        return $initials;
    }
    
    protected function _getIsoLanguage()
    {
        return 'NL';
    }
    
    protected function _getPhoneNumber($type)
    {
        if ( isset( $this->_additionalFields['phonenumber'] ) )
        {
            $number = $this->_additionalFields['phonenumber'];
        } else {
        
            if ($type == 'shipping') {
                $number = $this->_shippingInfo['telephone'];
            } else {
                $number = $this->_billingInfo['telephone'];
            }
        }
        
        //the final output must like this: 0031123456789 for mobile: 0031612345678
        //so 13 characters max else number is not valid
        //but for some error correction we try to find if there is some faulty notation
        
        $return = array("orginal" => $number, "clean" => false, "mobile" => false, "valid" => false);
        //first strip out the non-numeric characters:
        $match = preg_replace('/[^0-9]/Uis', '', $number);
        if ($match) {
            $number = $match;
        }
        if($this->_country == 'nlnl') {
            if (strlen((string)$number) == 13) {
                //if the length equal to 13 is, then we can check if its a mobile number or normal number
                $return = $number;
            } elseif (strlen((string) $number) > 13) {
                //if the number is bigger then 13, it means that there are probably a zero to much
                $return = $this->_isValidNotation($number);
            } elseif (strlen((string)$number) == 12 or strlen((string)$number) == 11) {
                //if the number is equal to 11 or 12, it means that they used a + in their number instead of 00 
                $return = $this->_isValidNotation($number);
            } elseif (strlen((string)$number) == 10) {
                //this means that the user has no trailing "0031" and therfore only
                $return = '0031'.substr($number,1);
            } else {
                //if the length equal to 13 is, then we can check if its a mobile number or normal number
                $return = $number;
            }
        } else {
            $return = $number;
        }
        
        return $return;
    }
    
    protected function _getGender()
    {
        $gender = 'V';
        if ($this->_isB2B) {
            $gender = '';
        }
        
        if ( isset( $this->_additionalFields['gender'] ) )
        {
            $gender = $this->_additionalFields['gender'];
        } elseif ($this->_order->getCustomerGender()) {
            $magentoGender = $this->_order->getCustomerGender();
            switch($magentoGender) {
                case '1':
                    $gender = 'M';
                    break;
                case '2':
                    $gender = 'V';
                    break;
            }
        } else {
            $gender = '';
        }
        return $gender;
    }
    
    protected function _getDob()
    {
        // Set variable for date of birth
        $dob = '';
        
        // First check if form birthday is set else get the birthday of the customer, otherwise error
        if ( isset( $this->_additionalFields['dob'] ) || isset( $this->_additionalFields['dob_year'] ) )
        {
            // Logics if javascript worked
            if ( array_key_exists( 'dob', $this->_additionalFields ) )
            {
                $dobTimestamp = strtotime( $this->_additionalFields['dob'], time() );
                $dob = date( 'Y-m-d\TH:i:s', $dobTimestamp );
            }
            // Logics if javascript for date has not worked
            elseif ( 
                array_key_exists( 'dob_year', $this->_additionalFields )
                && array_key_exists( 'dob_month', $this->_additionalFields )
                && array_key_exists( 'dob_day', $this->_additionalFields )
            )
            {
                $dobdate = $this->_additionalFields['dob_year'] . '-' . $this->_additionalFields['dob_month'] . '-' . 
                    $this->_additionalFields['dob_day'];
                $dobTimestamp = strtotime($dobdate, time());
                $dob = date('Y-m-d\TH:i:s', $dobTimestamp);
            }
        }
        // No birthday sent through form fields, look if a birthday was sent using Magento default fields
        elseif( $this->_order->getCustomerDob() )
        {
            $dobdate = $this->_order->getCustomerDob();
            $dobTimestamp = strtotime( $dobdate, time() );
            $dob = date('Y-m-d\TH:i:s', $dobTimestamp);
        }
        // If the variable $dob is not filled, then there was a problem with getting the correct date of birth
        // Because sending an empty value will cause SOAP error, do Mage Exception instead
        if ($dob == '')
        {
            // Cancel the order to prevent pending orders
            $this->_order->cancel()->save();
            // Restore the quote to keep cart information
            $this->restoreQuote();
            // Sent back error
            Mage::throwException($this->_helper->__('The date of birth is missing invalid. Please check your date or birth or contact our customer service.'));
        }
        
        return $dob;
    }
    
    protected function _isValidNotation($number) {
        //checks if the number is valid, if not: try to fix it
        $invalidNotations = array("00310", "0310", "310", "31");
        foreach($invalidNotations as $invalid) {
            if( strpos( substr( $number, 0, 6 ), $invalid ) !== false ) {
                $valid = substr($invalid, 0, -1);
                if (substr($valid, 0, 2) == '31') { 
                    $valid = "00" . $valid;
                }
                if (substr($valid, 0, 2) == '03') { 
                    $valid = "0" . $valid;
                }
                if ($valid == '3'){ 
                    $valid = "0" . $valid . "1";
                }
                $number = str_replace($invalid, $valid, $number);
            }
        }
        return $number;
    }
    
    protected function _splitStreet($address)
    {
        $ret = array(
            'streetName'          => '',
            'houseNumber'         => '',
            'houseNumberAddition' => '',
        );
        if (preg_match('#^(.*?)([0-9]+)(.*)#s', $address, $matches)) {
            if ('' == $matches[1]) {
                // Number at beginning
                $ret['houseNumber'] = trim($matches[2]);
                $ret['streetName']     = trim($matches[3]);
            } else {
                // Number at end
                $ret['streetName']            = trim($matches[1]);
                 $ret['houseNumber']         = trim($matches[2]);
                 $ret['houseNumberAddition'] = trim($matches[3]);
            }
        } else {
             // No number
            Mage::throwException(Mage::helper('afterpay')->__('Please enter a housenumber.'));
        }
        
         return $ret;
    }
    
    /**
     * Stores the current capture mode in the order object for future reference
     */
    protected function _storeCaptureMode()
    {
        $captureMode = Mage::getStoreConfig('afterpay/afterpay_capture/capture_mode', $this->_order->getStoreId());
        
        $this->_order->setAfterpayCaptureMode($captureMode)->save();
    }
}