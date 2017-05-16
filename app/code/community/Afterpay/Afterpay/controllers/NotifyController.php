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
 
 class Afterpay_Afterpay_NotifyController extends Mage_Core_Controller_Front_Action
{
    public function pushAction()
    {
        Mage::log('called', null, 'Afterpay_Push.log', true);
        Mage::log($this->getRequest()->getPost(), null, 'Afterpay_Push.log', true);
        
        $pushMessage = $this->getRequest()->getPost();
        $order = Mage::getModel('sales/order')->loadByIncrementId($pushMessage['orderReference']);
        
        // merchantId + portefeuilleId + password + orderReference + statusCode
        $pushpassword = Mage::getStoreConfig('afterpay/afterpay_general/push_password', Mage::app()->getStore()->getId());
        Mage::log($pushpassword, null, 'Afterpay_Push.log', true);
        $hash = $pushMessage['merchantId'] . $pushMessage['portefeuilleId'] . $pushpassword . $pushMessage['orderReference'] . $pushMessage['statusCode'];
        $checksum = md5($hash);
        if ($checksum != $pushMessage['signature']) {
            Mage::log('Error in push, checksum not correct.' , null, 'Afterpay_Push.log', true);
            return;
        }
        
        switch ($pushMessage['statusCode']) {
            case 'A':
                $this->acceptOrder($order);
                break;
            case 'W':
                $this->cancelOrder($order);
                break;
            case 'V':
                $this->cancelOrder($order);
                break;
            case 'P':
                $this->updateStatusOrder($order,$pushMessage['subStatusCode']);
                break;
            default :
                Mage::log('Error in status of the push message, result is the message is not handled', null, 'Afterpay_Push.log', true);
                break;
        }
    }

    protected function cancelOrder($order){
        $response = Mage::getModel('afterpay/response_abstract');
        $response->setCurrentOrder($order);
        $response->setRejectMessage('Reject');
        $response->setRejectDescription('Rejected by AfterPay');
        $response->_rejectFinal();
    }

   protected function acceptOrder($order){
        
        $response = Mage::getModel('afterpay/response_abstract');
        $response->setCurrentOrder($order);
        $order->setAfterpayOrderReference($order->getid());
        $order->setAfterpayTransactionId($order->getid());
        $order->save();
        $response->_updateAndInvoice();
    }


    protected function updateStatusOrder($order,$message){
        Mage::log('Order Payment is still Pending but there is a update on the status for Order :'.$order->getid().' with sub status :'.$message, null, 'Afterpay_Push.log', true);
        $order->addStatusHistoryComment($message);
        $order->save();
    }

    public function indexAction()
    {
        Mage::log('called index', null, 'Afterpay_Push.log', true);
        Mage::log($this->getRequest()->getPost(), null, 'Afterpay_Push.log', true);
    }
}