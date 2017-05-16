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
 
class Afterpay_Afterpay_Model_Response_Refund extends Afterpay_Afterpay_Model_Response_Abstract
{
    protected $_totalInvoicedAmount = 0;

    protected function _construct()
    {
        $this->setHelper(Mage::helper('afterpay'));
    }
    
    public function processResponse()
    {
        if ($this->_response === false) {
            $this->_debugEmail .= "An error occurred in building or sending the SOAP request.. \n";
            return $this->_error();
        }
        
        $this->_debugEmail .= "verifiying authenticity of the refund response... \n";
        $verified = $this->_verifyResponse();

        if ($verified !== true) {
            $this->_debugEmail .= "The authenticity of the refund response could NOT be verified. \n";
            return $this->_verifyError();
        }
        $this->_debugEmail .= "Verified as authentic! \n\n";
        
        $requiredAction = $this->_parseResponse();
        $this->_debugEmail .= 'Parsed response: ' . $requiredAction . "\n";
        
        $return = $this->_requiredAction($requiredAction);
        
        $this->sendDebugEmail();
        
        return $return;
    }
    
    protected function _verifyResponse()
    {
        $verified = false;
        
        //save response XML to string
        $responseDomDoc = $this->_responseXML;
        $responseDomDoc->saveXML();

        $resultId = (int) $this->_response->return->resultId;
        
        $verified = $this->_verifySignature();
        
        if ($resultId !== 0) {
            $verified = true;
        }
        
        return $verified;
    }

    protected function _verifySignature()
    {
        $this->_debugEmail .= "verifying signature of the response...\n";
        $verified = false;

        $method = $this->_order->getPayment()->getMethod();
        $testMode = (bool) Mage::getStoreConfig('afterpay/afterpay_' . $method . '/mode', $this->_order->getStoreId());
        
        $merchantId = Mage::getStoreConfig('afterpay/afterpay_' . $method . '/live_merchant_id', $this->_order->getStoreId());
        
        if ($testMode) {
            $merchantId = Mage::getStoreConfig('afterpay/afterpay_' . $method . '/test_merchant_id', $this->_order->getStoreId());
        }
        
        $checksum      = $this->_response->return->checksum;
        $totalInvoicedAmount = $this->_response->return->totalInvoicedAmount;
        $totalAmount   = (abs($totalInvoicedAmount) != $totalInvoicedAmount) ? 0 : $totalInvoicedAmount;
        $this->_totalInvoicedAmount = $totalAmount;
        $resultId      = $this->_response->return->resultId;
        $transactionId = $this->_response->return->transactionId;
        $orderId       = $this->_order->getIncrementId();
        
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

    protected function _accept()
    {
        $this->_debugEmail .= "The response indicates a successful refund request. \n";
        
        $this->_order->addStatusHistoryComment($this->_helper->__('This order has been refunded by AfterPay'))->save();
        
        if($this->_totalInvoicedAmount == 0) {
            $this->_order->setData('state', Mage_Sales_Model_Order::STATE_CLOSED)->save();
        }
        
        return true;
    }

    protected function _pending()
    {
        $this->_debugEmail .= "The response is neutral (not successful, not unsuccessful). \n";

        Mage::throwException($this->_helper->__('Unable to accept refund.'));
        
        return true;
    }

    protected function _validation()
    {
        $this->_debugEmail .= "The refund request generated a validation error. \n";

        $this->_order->addStatusHistoryComment($this->_helper->__('AfterPay refund attempt has failed'))->save();
                   
        Mage::throwException($this->_helper->__('Unable to refund order.'));

        return false;
    }
    
    protected function _error()
    {
        $this->_debugEmail .= "The refund request generated an error. \n";

        $this->_order->addStatusHistoryComment($this->_helper->__('AfterPay refund attempt has failed'))->save();
                         
        Mage::throwException($this->_helper->__('Unable to refund order.'));

        return false;
    }

    protected function _verifyError()
    {
        $this->_debugEmail .= "Could not verify authenticity of refund response";
        
        $this->_order->addStatusHistoryComment($this->_helper->__('Could not verify the authenticity of the refund response.'))->save();
        
        Mage::throwException($this->_helper->__('Could not verify the authenticity of the refund response.'));
        
        return false;
    }
}