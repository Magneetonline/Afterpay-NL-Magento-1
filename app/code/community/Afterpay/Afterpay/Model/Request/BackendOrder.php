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
 
 class Afterpay_Afterpay_Model_Request_BackendOrder extends Afterpay_Afterpay_Model_Request_Abstract
{
    protected function _construct()
    {
        $this->setHelper(Mage::helper('afterpay'));
    }
    
    public function sendRequest()
    {
        $this->_debugEmail .= 'Chosen portfolio: ' . $this->_method . "\n";
        
        $this->_storeCaptureMode();

        $responseModel = Mage::getModel('afterpay/response_backendOrder');
            
        //if no method has been set (no payment method could identify the chosen method) process the order as if it had failed
        if (empty($this->_method)) {
            $this->_debugEmail .= "No method was set! \n";
            
            $responseModel->setOrder($this->_order)
                          ->setResponse(false)
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
            $responseModel->sendDebugEmail();
            $this->logException($exception);
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
             ->setIsB2B($this->getIsB2B());
        
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
        $responseModel->setOrder($this->_order)
                      ->setResponse($response)
                      ->setResponseXML($responseXML)
                      ->setDebugEmail($this->_debugEmail)
                      ->setRequest($this);
        
        try {
            return $responseModel->processResponse();
        } catch (Exception $exception) {
            $this->logException($exception);
            return false;
        }
    }
}