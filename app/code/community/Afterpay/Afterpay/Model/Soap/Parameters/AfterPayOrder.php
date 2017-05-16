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
 
 class Afterpay_Afterpay_Model_Soap_Parameters_AfterPayOrder
{
    public $ordernumber;
    public $parentTransactionreference;
    public $bankID;
    public $bankaccountNumber;
    public $currency; //currently only EUR is allowed
    public $exchangeDate;
    public $exchangeRate; //example: 1CHF = 0,91383 EUR. Then value of this field will be 0,91383
    public $ipAddress; //IPv4, IPv6 ready in Q1/2013
    public $totalOrderAmount; //amount inc. tax in eurocents
    public $totalOrderNetAmount; //amount ex. tax in eurocents with 2 decimals //only enter if shop wants to invoice ex. VAT
    public $orderlines; //array of order lines
    public $extrafields; //array of extra info that does not fit within current webservice request
    public $shopdetails;
    public $shopper; //customr info
    public $person; //info of contact within company who places order on behalf of company
}