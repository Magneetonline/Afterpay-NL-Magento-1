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
 
 class Afterpay_Afterpay_Model_Response_Abstract extends Afterpay_Afterpay_Model_Abstract
{
    protected $_debugEmail = '';
    protected $_responseXML = '';
    protected $_response = null;
    protected $_customResponseProcessing = false;
    protected $_request;

    public function setCurrentOrder($order)
    {
        $this->_order = $order;
        return $this;
    }

    public function getCurrentOrder()
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

    public function setResponseXML($yml)
    {
        $this->_responseXML = $yml;
        return $this;
    }

    public function getResponseXML()
    {
        return $this->_responseXML;
    }

    public function setResponse($response)
    {
        $this->_response = $response;
        return $this;
    }

    public function getResponse()
    {
        return $this->_response;
    }

    public function setRequest($request)
    {
        $this->_request = $request;
        return $this;
    }

    public function getRequest()
    {
        return $this->_request;
    }

    public function processResponse()
    {
        if (is_null($this->_response)) {
            Mage::throwException($this->_helper('No response was available'));
        }
        
        if ($this->_response === false) {
            $this->_debugEmail .= "An error occurred in building or sending the SOAP request.. \n";
            return $this->_error();
        }
        
        $this->_debugEmail .= "verifiying authenticity of the response... \n";
        $verified = $this->_verifyResponse();

        if ($verified !== true) {
            $this->_debugEmail .= "The authenticity of the response could NOT be verified. \n";
            return $this->_verifyError();
        }
        $this->_debugEmail .= "Verified as authentic! \n\n";
        
        $requiredAction = $this->_parseResponse();
        $this->_debugEmail .= 'Parsed response: ' . $requiredAction . "\n";

        $this->_debugEmail .= "Dispatching custom order processing event... \n";
        Mage::dispatchEvent(
            'afterpay_response_custom_processing',
            array(
                'model'         => $this,
                'order'         => $this->_order,
                'response'      => $this->_response,
            )
        );

        $return = $this->_requiredAction($requiredAction);
        
        $this->sendDebugEmail();
        
        return $return;
    }
    
    protected function _parseResponse()
    {
        if (array_key_exists($this->_response->return->resultId, $this->responseCodes)) {
            $response = $this->responseCodes[$this->_response->return->resultId];
        } else {
            $response = false;
        }
        
        switch ($response) {
            case self::AFTERPAY_SUCCESS:            $requiredAction = 'accept';
                                                    break;
            case self::AFTERPAY_ERROR:              $requiredAction = 'error';
                                                    break;
            case self::AFTERPAY_FAILED:             $requiredAction = 'failed';
                                                    break;
            case self::AFTERPAY_REJECTED:           $requiredAction = 'reject';
                                                    break;
            case self::AFTERPAY_PENDING_PAYMENT:    $requiredAction = 'pending';
                                                    break;
            case self::AFTERPAY_VALIDATION_ERROR:   $requiredAction = 'validation';
                                                    break;
            default:                                $requiredAction = 'pending';
        }
        
        return $requiredAction;
    }

    protected function _requiredAction($response)
    {
        try {
            $response = '_' . $response;
        } catch (Exception $exception) {
            return $this->_error();
        }
        return $this->$response();
    }

    protected function _accept()
    {
        $this->_debugEmail .= "The response indicates a successful request. \n";
        if(!$this->_order->getEmailSent())
        {
            $isEnterprise = false;
            if (Mage::helper('core')->isModuleEnabled('Enterprise_Enterprise')) {
                $isEnterprise = true;
            }
            $magentoVersion = Mage::getVersion();
            if($isEnterprise == false) {
                if (version_compare($magentoVersion, '1.9.1', '>=')){
                    $this->_order->queueNewOrderEmail();
                } else {
                    $this->_order->sendNewOrderEmail();
                }
            } else {
                if (version_compare($magentoVersion, '1.13.1', '>=')){
                    $this->_order->queueNewOrderEmail();
                } else {
                    $this->_order->sendNewOrderEmail();
                }
            }
        }
        
        $this->_storeAfterPayOrderReference();
        $this->_storeAfterPayTransactionId();
        
        if (
            array_key_exists($this->_response->return->statusCode, $this->responseCodes)
            && $this->responseCodes[$this->_response->return->statusCode] == self::AFTERPAY_ACCEPTED
            && $this->_order->canInvoice()
        ) {
            $this->_updateAndInvoice();
        }
        
        Mage::getSingleton('core/session')->addSuccess(
            $this->_helper->__('Your order has been placed succesfully.')
        );
        
        return true;
    }

    protected function _failed()
    {
        $this->_debugEmail .= 'The transaction was unsucessful. \n';
        Mage::getSingleton('core/session')->addError(
            $this->_helper->__('Your order was unsuccesful. Please try again or choose another payment method.')
        );

        $this->_order->setStatus(Mage::getStoreConfig('afterpay/afterpay_' . $this->_order->getPayment()->getMethod() . '/order_status_refused', $this->_order->getStoreId()))->cancel()->save();
        
        $this->_debugEmail .= "The order has been cancelled. \n";
        $this->restoreQuote();
        $this->_debugEmail .= "The quote has been restored. \n";

        return false;
    }

    /**
     * Method to process an authorization request that has been rejected. If so configured, this method will attempt a second authorization
     * for the risk portfolio ID specified. In order to do so it will call Afterpay_Afterpay_Model_Request_Risk::sendRequest().
     * This code is almost identical to Afterpay_Afterpay_Model_Request_Abstract::sendRequest() except that it uses all variables defined in said
     * method, rather than redefine them.
     * 
     * If this also causes this method to be called, it will instead cancel the order.
     * 
     * @param boolean $isRisk
     */
    protected function _reject()
    {
        $this->_debugEmail .= 'The transaction was unsuccessful. \n';
        
        return $this->_rejectFinal();
    }
    
    public function _rejectFinal()
    {
        $rejectMessage = $this->_getRejectMessage();
        $rejectDescription = $this->_getRejectDescription();
        
        Mage::getSingleton('core/session')->addError(
            $this->_helper->__($rejectMessage)
        );
        
        $this->_order->addStatusHistoryComment($rejectDescription);
        
        $this->_order->setStatus(Mage::getStoreConfig('afterpay/afterpay_' . $this->_order->getPayment()->getMethod() . '/order_status_refused', $this->_order->getStoreId()))->save();
        $this->_order->cancel()->save();
        
        $this->_debugEmail .= "The order has been cancelled. \n";
        $this->restoreQuote();
        $this->_debugEmail .= "The quote has been restored. \n";

        return array('response'=>false, 'error'=>'rejection');
    }
    
    protected function _error()
    {
        $this->_debugEmail .= "The transaction generated an error. \n";
        Mage::getSingleton('core/session')->addError(
            $this->_helper->__('A technical error has occurred. Please try again. If this problem persists, please contact the shop owner.')
        );

        $this->_order->cancel()->save();
        $this->_debugEmail .= "The order has been cancelled. \n";
        $this->restoreQuote();
        $this->_debugEmail .= "The quote has been restored. \n";

        return false;
    }

    protected function _pending()
    {
        $this->_debugEmail .= "The response is neutral (not successful, not unsuccessful). \n";

        Mage::getSingleton('core/session')->addSuccess(
            $this->_helper->__(
                'Your order has been placed succesfully. You will recieve an e-mail containing further payment instructions shortly.'
            )
        );
        
        $this->_order->setStatus(Mage::getStoreConfig('afterpay/afterpay_' . $this->_order->getPayment()->getMethod() . '/order_status_pending', $this->_order->getStoreId()))->save();
        
        if($this->_response->return->extrafields->nameField == 'redirectUrl')
        {
            $return = array(
                'response' => true,
                'redirect' => true,
                'redirecturl' => $this->_response->return->extrafields->valueField,
                'resultid' => $this->_response->return->resultId
            );
        } else {
            $return = array(
                'response' => true,
                'redirect' => false,
                'redirecturl' => '',
                'resultid' => $this->_response->return->resultId
            );
        }

        return $return;
    }

    protected function _validation()
    {
        $this->_debugEmail .= "The response indicates a validation error. \n";

        if(!is_array($this->_response->return->failures))
        {
            $failures[] = $this->_response->return->failures;
            $this->_response->return->failures = $failures;
        }
        
        foreach($this->_response->return->failures as $failure) {
            Mage::getSingleton('core/session')->addError(
                $this->_helper->__(Mage::helper('afterpay')->checkValidationError( $failure ) )
            );
            $this->_debugEmail .= 'Failure: ' . var_export($failure, true) . "\n";
        }
        
        $this->_order->cancel()->save();
        $this->_debugEmail .= "The order has been cancelled. \n";
        $this->restoreQuote();
        $this->_debugEmail .= "The quote has been restored. \n";
        
        return array('response'=>false, 'error'=>'validation');
    }

    protected function _verifyError()
    {
        $this->_debugEmail .= "The transaction's authenticity was not verified. \n";
        Mage::getSingleton('core/session')->addNotice(
            $this->_helper->__('We are currently unable to retrieve the status of your transaction. If you do not recieve an e-mail regarding your order within 30 minutes, please contact the shop owner.')
        );
        
        $this->_debugEmail .= "The quote has been restored. \n";
        
        return false;
    }

    protected function _verifyResponse()
    {
        $verified = false;
        
        //save response XML to string
        $responseDomDoc = $this->_responseXML;
        $responseDomDoc->saveXML();

        $resultId = (int) $this->_response->return->resultId;
        if ($resultId !== 0) {
            $verified = true;
        } else {
            $verified = $this->_verifySignature();
        }

        return $verified;
    }

    protected function _verifySignature()
    {
        $this->_debugEmail .= "verifying signature of the response...\n";
        $verified = false;

        $method = $this->_order->getPayment()->getMethod();
        $testMode = (bool) Mage::getStoreConfig('afterpay/afterpay_' . $method . '/mode', $this->_order->getStoreId());
        
        if ($testMode) {
            $merchantId = Mage::getStoreConfig('afterpay/afterpay_' . $method . '/test_merchant_id', $this->_order->getStoreId());
        } else {
            $merchantId = Mage::getStoreConfig('afterpay/afterpay_' . $method . '/live_merchant_id', $this->_order->getStoreId());
        }
        
        $checksum      = $this->_response->return->checksum;
        $totalAmount   = round($this->_order->getBaseGrandTotal() * 100, 0);
        $resultId      = $this->_response->return->resultId;
        $transactionId = $this->_response->return->transactionId;
        $orderId       = $this->_order->getIncrementId();
       
        $orderId       = $this->_isRisk ? $orderId . '-R' : $orderId;
        
        $signatureString = $merchantId 
                         . '-'
                         . $totalAmount 
                         . '-'
                         . $resultId 
                         . '-'
                         . $transactionId 
                         . '-'
                         . $orderId;

        $this->_debugEmail .= "\nSignature string: {$signatureString}\n";
        $signature = MD5($signatureString);
        $this->_debugEmail .= "signature: {$signature}\n";
        
        if ($signature === $checksum) {
            $this->_debugEmail .= "Signature matches Afterpay's checksum!\n";
            $verified = true;
        }
        
        return $verified;
    }
    
    public function _updateAndInvoice()
    {
        $this->_order->addStatusHistoryComment($this->_helper->__('This order has been accepted by AfterPay.'));
        $this->_order->save();
        
        $this->_order->setStatus(Mage::getStoreConfig('afterpay/afterpay_' . $this->_order->getPayment()->getMethod() . '/order_status_acceptedni', $this->_order->getStoreId()))->save();
        
        try {
            $payment = $this->_order->getPayment();
            $this->_debugEmail .= "Attempting to capture order.\n";
            if (Mage::getStoreConfig('afterpay/afterpay_general/auto_invoice', $this->_order->getStoreId()) === 'yes') {
                
                // Check if capture mode is manual, if so only create the invoice
                if (Mage::getStoreConfig('afterpay/afterpay_capture/capture_mode', Mage::app()->getStore()->getId()) == '1') {
                    $invoice = Mage::getModel('sales/service_order', $this->_order)->prepareInvoice();
                    $invoice->register();
                } else {
                    $payment->registerCaptureNotification($this->_order->getBaseGrandTotal());
                    $this->_order->setTotalPaid($this->_order->getBaseGrandTotal());
                    $this->_order->setStatus(Mage::getStoreConfig('afterpay/afterpay_' . $this->_order->getPayment()->getMethod() . '/order_status_accepted', $this->_order->getStoreId()))->save();
                }
                
                if(Mage::getStoreConfig('afterpay/afterpay_general/send_invoice', Mage::app()->getStore()->getId()) == '1') {
                    $invoice = $this->_order->getInvoiceCollection()->getLastItem();
                    $invoice->sendEmail();
                    $invoice->setEmailSent(true);
                    $invoice->save();
                }
            }
        } catch (Exception $exception) {
            $this->_debugEmail .= 'capture has failed. Reason: ' . $exception->getMessage() . "\n";
            $this->_order->addStatusHistoryComment($exception->getMessage());
            $this->_order->setStatus(Mage::getStoreConfig('afterpay/afterpay_capture/order_status_refused', $this->_order->getStoreId()));
            $this->_order->save();
        }
        
        $this->_storeAfterPayInvoiceId();
        $this->_order->save();
    }
    
    protected function _storeAfterPayTransactionId()
    {
        $transactionId = $this->_response->return->transactionId;
        
        $this->_order->setAfterpayTransactionId($transactionId);
        $this->_order->save();
    }
    
    protected function _storeAfterPayOrderReference()
    {
        $orderReference = $this->_response->return->afterPayOrderReference;
        
        $this->_order->setAfterpayOrderReference($orderReference);
        $this->_order->save();
    }
    
    protected function _storeAfterPayInvoiceId()
    {
        foreach($this->_order->getInvoiceCollection() as $invoice)
        {
            $invoice->setTransactionId($this->_response->return->afterPayOrderReference)
                    ->save();
        }
    }
    
    protected function _getRejectMessage()
    {
        if (isset($this->_response->return->rejectCode)) {
            $rejectCode = (int) $this->_response->return->rejectCode;
            
            $country = Mage::getStoreConfig(
                'afterpay/afterpay_' . $this->_order->getPayment()->getMethod() . '/portfolio_country', 
                Mage::app()->getStore()->getId()
            );
        
            if($country == 'dede') {
                $rejectCode = 'de';
            }
            
            if($country == 'benl') {
                $rejectCode = 'be' . $rejectCode;
            }
            
        } else {
            $rejectCode = 1;
            
            $country = Mage::getStoreConfig(
                'afterpay/afterpay_' . $this->_order->getPayment()->getMethod() . '/portfolio_country', 
                Mage::app()->getStore()->getId()
            );
            
            if($country == 'dede') {
                $rejectCode = 'de';
            }
            
            if($country == 'benl') {
                $rejectCode = 'be' . $rejectCode;
            }
            
            $advisoryprocess = Mage::getStoreConfig('afterpay/afterpay_' . $this->_order->getPayment()->getMethod() . '/advisoryprocess', 
                $this->_order->getStoreId()
            );
            
            if ($advisoryprocess == '1') {
                $rejectCode = 'bp';
            }
        }
        
        $messageBlock = Mage::getBlockSingleton('afterpay/rejectMessages');
        
        $rejectMessage = $messageBlock->setRejectTemplate($rejectCode)->toHtml();
        
        return $rejectMessage;
    }
    
    protected function _getRejectDescription()
    {
        if (isset($this->_response->return->rejectCode)) {
            $rejectCode = (int) $this->_response->return->rejectCode;
        } else {
            $rejectCode = 1;
        }
        
        $messageBlock = Mage::getBlockSingleton('afterpay/rejectDescriptions');
        $rejectDescription = $messageBlock->setRejectDescription($rejectCode)->toHtml();
        
        return $rejectDescription;
    }
}