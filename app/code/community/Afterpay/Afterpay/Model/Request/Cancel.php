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
 
 class Afterpay_Afterpay_Model_Request_Cancel extends Afterpay_Afterpay_Model_Request_Refund
{    
    public function sendCancelRequest()
    {
        $this->_isCancelAllowed();
        
        $responseModel = Mage::getModel('afterpay/response_cancel');
        
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
        $this->_addOrderVariables();
        
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
        $soap = Mage::getModel('afterpay/soap_cancel');
        $soap->setvars($this->getVars())
             ->setMethod($this->getMethod())
             ->setIsPartial($this->getIsPartial());
             
        list($response, $responseXML, $requestXML) = $soap->cancelRequest();
        
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
    
    protected function _isCancelAllowed()
    {
        $captureModeUsed = $this->_order->getAfterpayCaptureMode();
        $captured = $this->_order->getAfterpayCaptured();
        
        if ($captureModeUsed == 1 && $captured == 1) {
            Mage::throwException($this->_helper->__('This order has already been captured by AfterPay.'));
        }
        
        if (!Mage::getStoreConfig('afterpay/afterpay_refund/enabled', $this->_order->getStoreId())) {
            Mage::throwException($this->_helper->__('Online refunding is disabled. Please us offline refunding or enable online refunding in the config.'));
        }
    }
}