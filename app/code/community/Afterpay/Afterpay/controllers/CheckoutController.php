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
 
 class Afterpay_Afterpay_CheckoutController extends Mage_Core_Controller_Front_Action
{
    public function checkoutAction()
    {
        try {
            $request = Mage::getModel('afterpay/request_abstract');
            $response = $request->sendRequest();
        } catch (Exception $exception) {
            $response = false;
            Mage::getSingleton('core/session')->addError(
                Mage::helper('afterpay')->__($exception->getMessage())
            );
        }
        
        if (is_array($response)) {
            if(isset($response['redirect']) && $response['redirect'] === true) {
                $this->_redirectUrl($response['redirecturl']);
            } else {
                if ($response['response'] === true) {
                    $successRedirectConfig = Mage::getStoreConfig('afterpay/afterpay_general/success_redirect', Mage::app()->getStore()->getId());
                    $redirectUrl = $successRedirectConfig ? $successRedirectConfig : 'checkout/onepage/success';
                } else {
                    switch ($response['error']) {
                        case 'rejection': 
                            $failureRedirectConfig = Mage::getStoreConfig('afterpay/afterpay_general/failure_redirect', Mage::app()->getStore()->getId());
                            break;
                        case 'validation':
                            $failureRedirectConfig = Mage::getStoreConfig('afterpay/afterpay_general/validation_redirect', Mage::app()->getStore()->getId());
                            break;
                    }
                    
                    $redirectUrl = $failureRedirectConfig ? $failureRedirectConfig : 'checkout/onepage/';
                }
                
                $this->_redirect($redirectUrl);
            }
        } else {
            if ($response === true) {
                $successRedirectConfig = Mage::getStoreConfig('afterpay/afterpay_general/success_redirect', Mage::app()->getStore()->getId());
                $redirectUrl = $successRedirectConfig ? $successRedirectConfig : 'checkout/onepage/success';
            } else {
                $failureRedirectConfig = Mage::getStoreConfig('afterpay/afterpay_general/failure_redirect', Mage::app()->getStore()->getId());
                $redirectUrl = $failureRedirectConfig ? $failureRedirectConfig : 'checkout/onepage/';
            }
            
            $this->_redirect($redirectUrl);
        }
    }
}