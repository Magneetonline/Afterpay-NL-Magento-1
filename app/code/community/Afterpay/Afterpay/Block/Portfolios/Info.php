<?php
class Afterpay_Afterpay_Block_Portfolios_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Afterpay/Afterpay/portfolios/info.phtml');
    }
    
    /**
     * Render as PDF
     * @return string
     */
    public function toPdf()
    {
        if (strpos($this->getMethod()->getCode(), 'portfolio_') !== false) {
            $this->setTemplate('Afterpay/Afterpay/portfolios/pdf.phtml');
        }
        return $this->toHtml();
    }

}