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
 
 class Afterpay_Afterpay_Model_Soap_Capture extends Afterpay_Afterpay_Model_Soap_Abstract
{
    public function captureRequest()
    {
        $param        = $this->_addCapture();
        $paramName    = 'captureobject';
        $functionName = 'captureFull';
        
        return $this->soapRequest('service', $functionName, $paramName, $param);
    }
    
    protected function _addCapture()
    {
        $captureObject   = Mage::getModel('afterpay/soap_parameters_capture');
        $invoiceLines    = $this->_addInvoiceLines();
        $transactionKey  = $this->_addTransactionKey();
        
        $captureObject->capturedelaydays     = $this->_vars['captureDelay'];
        $captureObject->invoicelines         = $invoiceLines;
        $captureObject->transactionkey       = $transactionKey;
        $captureObject->shippingCompany      = $this->_vars['shippingMethodTitle'];
        $captureObject->invoicenumber        = $this->_vars['invoiceId'];
        
        $captureObject = $this->_cleanEmptyValues($captureObject);
        
        return $captureObject;
    }
    
    protected function _addInvoiceLines()
    {
        $invoiceLines = array();
        
        if (!array_key_exists('orderLines', $this->_vars)) {
            return false;
        }
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
            
            $invoiceLines[] = $orderLine;
        }
        
        $invoiceLines = $this->_cleanEmptyValues($invoiceLines);
        
        return $invoiceLines;
    }
    
    protected function _addTransactionKey()
    {
        $transactionKey = Mage::getModel('afterpay/soap_parameters_transactionKey');
        
        //$transactionKey->parentTransactionreference = $this->_vars['parentTransactionReference'];
        $transactionKey->ordernumber                = $this->_vars['orderNumber'];
        
        $transactionKey = $this->_cleanEmptyValues($transactionKey);
        
        return $transactionKey;
    }
}