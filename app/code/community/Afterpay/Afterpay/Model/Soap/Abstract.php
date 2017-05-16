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
 
 
class Afterpay_Afterpay_Model_Soap_Abstract extends Mage_Core_Model_Abstract
{
    const WSDL_URL              = 'https://www.acceptgirodienst.nl/soapservices/rm/AfterPaycheck?wsdl';
    const TEST_WSDL_URL         = 'https://test.acceptgirodienst.nl/soapservices/rm/AfterPaycheck?wsdl';
    const SERVICE_WSDL_URL      = 'https://www.acceptgirodienst.nl/soapservices/om/OrderManagement?wsdl';
    const TEST_SERVICE_WSDL_URL = 'https://test.acceptgirodienst.nl/soapservices/om/OrderManagement?wsdl';

    protected $_testMode = false;
    protected $_vars;
    protected $_method;
    protected $_debugEmail;
    protected $_country;
    protected $_useSoapServices;
    
    public function getTestMode()
    {
        return $this->_testMode;
    }
    
    public function setTestMode($testMode = false)
    {
        $this->_testMode = $testMode;
        return $this;
    }
    
    public function getVars()
    {
        return $this->_vars;
    }
    
    public function setVars($vars = array())
    {
        $this->_vars = $vars;
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
    
    public function getMethod()
    {
        return $this->_method;
    }
    
    public function setMethod($method = '')
    {
        $this->_method = $method;
        return $this;
    }
    
    public function setUsesoapservices($useSoapServices = false) 
    {
        $this->_useSoapServices = $useSoapServices;
        return $this;
    }
    
    public function getUsesoapservices()
    {
        return $this->_useSoapServices;
    }
    
    public function soapRequest($clientType, $functionName, $paramName, $param)
    {
        $client = $this->_getCorrectClient($clientType);
        $authorization = $this->_getAuthorization();
        
        try {
            $response = $client->__soapCall(
                $functionName, 
                array(
                    $functionName => array(
                        'authorization' => $authorization, 
                        $paramName      => $param,
                    ),
                )
            );
        } catch (SoapFault $exception) {
            Mage::helper('afterpay')->logException($exception);
            return $this->_error($client);
        } catch (Exception $exception) {
            Mage::helper('afterpay')->logException($exception);
            return $this->_error($client);
        }
        if (is_null($response)) {
            $response = false;
        }
        
        $responseXML = $client->__getLastResponse();
        $requestXML = $client->__getLastRequest();
        
        $responseDomDOC = new DOMDocument();
        $responseDomDOC->loadXML($responseXML);
        $responseDomDOC->preserveWhiteSpace = FALSE;
        $responseDomDOC->formatOutput = TRUE;
        
        $requestDomDOC = new DOMDocument();
        $requestDomDOC->loadXML($requestXML);
        $requestDomDOC->preserveWhiteSpace = FALSE;
        $requestDomDOC->formatOutput = TRUE;

        return array($response, $responseDomDOC, $requestDomDOC);
    }
    
    /**
     * Method that attempts to retrieve a SoapClient instance in WSDL mode.
     * The method first attempts using a cached version of the WSDL. If that fails, it tries a non-cached version. If that also fails,
     * it will use a local version that is provided with this module
     */
    protected function _getCorrectClient($wsdlType)
    {
        try {
            $client = $this->_getClient($wsdlType, WSDL_CACHE_DISK);
        } catch (SoapFault $e) {
            try {
                $client = $this->_getClient($wsdlType, WSDL_CACHE_NONE);
            } catch (SoapFault $e) {
                try {
                    $client = $this->_getClient($wsdlType, 'local');
                } catch (SoapFault $e) {
                    Mage::helper('afterpay')->logException($e);
                    $this->_error();
                }
            }
        }
        
        return $client;
    }
    
    protected function _getClient($wsdlType, $cacheMode = WSDL_CACHE_NONE)
    {
        if ($cacheMode == 'local') {
            $wsdl = $this->_getLocalWsdl($wsdlType);
        } elseif ($this->_testMode) {
            $wsdl = $this->_getTestWsdlUrl($wsdlType);
        } else {
            $wsdl = $this->_getWsdlUrl($wsdlType);
        }
        
        $endpoints['nlnl']['test']['rm'] = 'https://test.acceptgirodienst.nl/soapservices/rm/AfterPaycheck?wsdl';
        $endpoints['nlnl']['test']['om'] = 'https://test.acceptgirodienst.nl/soapservices/om/OrderManagement?wsdl';
        $endpoints['nlnl']['live']['rm'] = 'https://www.acceptgirodienst.nl/soapservices/rm/AfterPaycheck?wsdl';
        $endpoints['nlnl']['live']['om'] = 'https://www.acceptgirodienst.nl/soapservices/om/OrderManagement?wsdl';
        $endpoints['benl']['test']['rm'] = 'https://test.afterpay.be/soapservices/rm/AfterPaycheck';
        $endpoints['benl']['test']['om'] = 'https://test.afterpay.be/soapservices/om/OrderManagement';
        $endpoints['benl']['live']['rm'] = 'https://mijn.afterpay.be/soapservices/rm/AfterPaycheck';
        $endpoints['benl']['live']['om'] = 'https://mijn.afterpay.be/soapservices/om/OrderManagement';
        $endpoints['dede']['test']['rm'] = 'https://clienttesthorizon.gothiagroup.com/eCommerceServices/AfterPay/RiskManagement/v2/RiskManagementServices.svc?singleWsdl';
        $endpoints['dede']['test']['om'] = 'https://clienttesthorizon.gothiagroup.com/eCommerceServices/AfterPay/OrderManagement/v2/OrderManagementServices.svc?wsdl';
        $endpoints['dede']['live']['rm'] = 'https://api.horizonafs.com/eCommerceServices/AfterPay/RiskManagement/v2/RiskManagementServices.svc?wsdl';
        $endpoints['dede']['live']['om'] = 'https://api.horizonafs.com/eCommerceServices/AfterPay/OrderManagement/v2/OrderManagementServices.svc?wsdl';            
        if ($this->_country == 'nlnl') {
            $client = new SoapClient(
                $wsdl,
                array(
                    'trace' => 1,
                    'cache_wsdl' => $cacheMode,
            ));
        } else {
            if ($this->_testMode) {
                if ($this->_useSoapServices) {
                    $location = $endpoints[$this->_country]['test']['om'];
                } else {
                    $location = $endpoints[$this->_country]['test']['rm'];
                }
            } else {
                if ($this->_useSoapServices) {
                    $location = $endpoints[$this->_country]['live']['om'];
                } else {
                    $location = $endpoints[$this->_country]['live']['rm'];
                }
            }
            
            $client = new SoapClient(
                $wsdl,
                array(
                    'location' => $location,
                    'trace' => 1,
                    'cache_wsdl' => $cacheMode,
            ));
        }
        
        return $client;
    }
    
    protected function _getWsdlUrl($wsdlType)
    {
        switch ($wsdlType) {
            case 'authorize':    return self::WSDL_URL;
                                 break;
            case 'service':      return self::SERVICE_WSDL_URL;
                                 break;
            default:             Mage::throwException('desired WSDL type not found. Requested WSDl type: ' . $wsdlType);
        }
    }
    
    protected function _getTestWsdlUrl($wsdlType)
    {
        switch ($wsdlType) {
            case 'authorize':    return self::TEST_WSDL_URL;
                                 break;
            case 'service':      return self::TEST_SERVICE_WSDL_URL;
                                 break;
            default:             Mage::throwException('desired WSDL type not found. Requested WSDl type: ' . $wsdlType);
        }
    }
    
    protected function _getLocalWsdl($wsdlType)
    {
        switch ($wsdlType) {
            case 'authorize':    return Mage::getBaseDir()
                                        . DS 
                                        . 'app' 
                                        . DS 
                                        . 'code' 
                                        . DS 
                                        . 'community' 
                                        . DS 
                                        . 'Afterpay' 
                                        . DS 
                                        . 'Afterpay' 
                                        . DS 
                                        . 'Model' 
                                        . DS 
                                        . 'Soap' 
                                        . DS 
                                        . 'Wsdl' 
                                        . DS 
                                        . 'AfterPaycheck_1.wsdl';
                                 break;
            case 'service':      return Mage::getBaseDir()
                                        . DS 
                                        . 'app' 
                                        . DS 
                                        . 'code' 
                                        . DS 
                                        . 'community' 
                                        . DS 
                                        . 'Afterpay' 
                                        . DS 
                                        . 'Afterpay' 
                                        . DS 
                                        . 'Model' 
                                        . DS 
                                        . 'Soap' 
                                        . DS 
                                        . 'Wsdl' 
                                        . DS 
                                        . 'OrderManagement_1.wsdl';
                                 break;
            default:             Mage::throwException('desired WSDL type not found. Requested WSDl type: ' . $wsdlType);
        }
    }
    
    protected function _getAuthorization()
    {
        $authorization = Mage::getModel('afterpay/soap_parameters_authorization');
        
        $authorization->merchantId    = $this->_vars['merchantId'];
        $authorization->portfolioId   = $this->_vars['portfolioId'];
        $authorization->password      = $this->_vars['password'];
        
        return $authorization;
    }
    
    protected function _cleanEmptyValues($object)
    {
        return $object;
    }
    
    protected function _error($client = false)
    {
        $response = false;
        
        $responseDomDOC = new DOMDocument();
        $requestDomDOC = new DOMDocument();
        if ($client) {
            $responseXML = $client->__getLastResponse();
            $requestXML = $client->__getLastRequest();
        
            if (!empty($responseXML)) {
                $responseDomDOC->loadXML($responseXML);
                $responseDomDOC->preserveWhiteSpace = FALSE;
                $responseDomDOC->formatOutput = TRUE;
            }
            
            if (!empty($requestXML)) {
                $requestDomDOC->loadXML($requestXML);
                $requestDomDOC->preserveWhiteSpace = FALSE;
                $requestDomDOC->formatOutput = TRUE;
            }
        }

        return array($response, $responseDomDOC, $requestDomDOC);
    }
}