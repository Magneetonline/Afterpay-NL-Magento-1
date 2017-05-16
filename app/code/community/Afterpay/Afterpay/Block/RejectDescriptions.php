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
 
 class Afterpay_Afterpay_Block_RejectDescriptions extends Mage_Core_Block_Abstract
{
    protected $_rejectTemplate;
    
    public $template1 = "Overig";
    
    public $template29 = "Te hoog eerste orderbedrag";
    
    public $template30 = "Maximale aantal openstaande betalingen bereikt";
    
    public $template36 = "Ongeldig emailadres";
    
    public $template40 = "Leeftijd onder 18 jaar";
    
    public $template42 = "Adres onjuist";
    
    public $template71 = "Onjuist KVK nummer en/of tenaamstelling";
    
    public function setRejectDescription($id = 1) {
        $templateId = 'template' . $id;
        
        $this->_rejectTemplate = $this->template1;
        
        if (isset($this->$templateId)) {
            $this->_rejectTemplate = $this->$templateId;
        }
        
        return $this;
    }
    
    protected function _toHtml()
    {
        return 'Rejected by AfterPay: ' . $this->_rejectTemplate;
    }
}