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
 
 class Afterpay_Afterpay_Block_Portfolios_Checkout_Form extends Mage_Payment_Block_Form
{
    public $shopName                         = '';
    public $maxOrderAmountNewCustomers       = '&#8364;';
    public $maxOrderAmountReturningCustomers = '&#8364;';
    public $anchorClose                      = '</a>';
    public $privacyStatementUrl              = '<a href="http://www.afterpay.nl/page/privacy-statement" target="_blank">';
    public $consumerContactUrl               = '<a href="http://www.afterpay.nl/page/consument-contact" target="_blank">';
    public $consumerPageUrl                  = '<a href="http://www.afterpay.nl/page/consument" target="_blank">';
    public $paymentConditionsUrl             = '<a href="http://www.afterpay.nl/page/consument-betalingsvoorwaarden" target="_blank" style="margin-top:0; float:none; margin-left:0">';
    public $country                          = 'nlnl';
    
    protected $_template = 'Afterpay/Afterpay/portfolios/checkout/form.phtml';
    
    public function __construct()
    {
        parent::__construct();
        
        // If IWD or OSC is used then use different form template
        if (Mage::helper('core')->isModuleEnabled('IWD_Opc') || Mage::helper('core')->isModuleEnabled('Idev_OneStepCheckout'))
        {
            $this->setTemplate('Afterpay/Afterpay/portfolios/checkout/form-iwd.phtml');
        }
    }
    
    public function setBlockData()
    {
        $shopName = Mage::getStoreConfig('general/store_information/name', Mage::app()->getStore()->getId());
        $this->shopName = $shopName ? $shopName : 'deze webshop';
        
        $newCustomerAmount = Mage::getStoreConfig(
            'afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_max_amount_new_customers', 
            Mage::app()->getStore()->getId()
        );
        
        $returningCustomerAmount = Mage::getStoreConfig(
            'afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_max_amount', 
            Mage::app()->getStore()->getId()
        );
        
        $this->maxOrderAmountNewCustomers .= round($newCustomerAmount, 2);
        $this->maxOrderAmountReturningCustomers .= round($returningCustomerAmount, 2);
        
        $this->country = Mage::getStoreConfig(
            'afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_country', 
            Mage::app()->getStore()->getId()
        );
        
        if($this->country == 'benl'){
            // Check if url is Belgium
            $this->privacyStatementUrl             = '<a href="https://www.afterpay.be/nl/klantenservice/privacy-statement/" target="_blank">';
            $this->consumerContactUrl            = '<a href="https://www.afterpay.be/nl/klantenservice/vraag-en-antwoord/" target="_blank">';
            $this->consumerPageUrl                = '<a href="https://www.afterpay.be/nl/klantenservice/vraag-en-antwoord/" target="_blank">';
            $this->paymentConditionsUrl            = '<a href="https://www.afterpay.be/nl/klantenservice/betalingsvoorwaarden/" target="_blank">';
        }
        
        
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->getMethod()->getCode() . '/advisoryprocess') == '1') {
            $this->paymentConditionsUrl            = '<a href="http://www.mijnbetaalplan.nl/index.cfm?fcPageName=Voorwaarden" target="_blank">';
        }
        
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_type') == 'B2B') {
            $this->paymentConditionsUrl            = '<a href="https://www.afterpay.nl/nl/klantenservice/betalingsvoorwaarden-b2b/" target="_blank">';
        }
    }
    
    public function getMethodLabelAfterHtml()
    {
        if(!$this->isAdvisoryprocess()) {
            $labelAfterHtml = '<img src="'. $this->getSkinUrl('images/Afterpay/Afterpay/afterpay.png') . '" />&nbsp;'.$this->getMethod()->getTitle();
        } else {
            $labelAfterHtml = '<img src="'. $this->getSkinUrl('images/Afterpay/Afterpay/mijnbetaalplan.png') . '" />&nbsp;'.$this->getMethod()->getTitle();
        }

        if (!$this->getMethod()->getFootnote()) {
            $labelAfterHtml .= '<span class = \'afterpay_paymentmethod_label afterpay_paymentmethod_label_' 
                        . $this->getMethod()->getCode() 
                        . '\'>';$this->getMethod()->getFootnote() . '</span>';
        }
        
        // If IWD is used then use no logo
        if (Mage::helper('core')->isModuleEnabled('IWD_Opc') || Mage::helper('core')->isModuleEnabled('Idev_OneStepCheckout'))
        {
            $labelAfterHtml = $this->getMethod()->getTitle();
        }
        
        return $labelAfterHtml;
    }
    
    public function hasMethodTitle()
    {
        return true;
    }
    
    public function getMethodTitle()
    {
        return '';
    }
    
    public function isB2B()
    {
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_type') == 'B2B') {
            return true;
        }
        return false;
    }
    
    public function showBankaccount()
    {
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_showbankaccount') == '1') {
            return true;
        }
        return false;
    }
    
    public function showGender()
    {
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_showgender') == '1') {
            return true;
        }
        return false;
    }
    
    public function showPhonenumber()
    {
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_showphonenumber') == '1') {
            return true;
        }
        return false;
    }
    
    public function showDob()
    {
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_showdob') == '1') {
            return true;
        }
        return false;
    }
    
    public function showTerms()
    {
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->getMethod()->getCode() . '/portfolio_showterms') == '1') {
            return true;
        }
        return false;
    }
    
    public function getCompany()
    {
        $billingAddress = Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress();
        
        return $billingAddress->getCompany();
    }
    

    public function isAdvisoryprocess()
    {
        if (Mage::getStoreConfig('afterpay/afterpay_' . $this->getMethod()->getCode() . '/advisoryprocess') == '1') {
            return true;
        }
        return false;
    }
}