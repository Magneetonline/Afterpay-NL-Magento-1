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
 
 $installer = $this;

$installer->startSetup();
$conn = $installer->getConnection();

/**
 * Add AfterPay columns
 */
$conn->addColumn(
    $installer->getTable('sales/order'),
    'afterpay_transaction_id',
    "varchar(32) null"
);
$conn->addColumn(
    $installer->getTable('sales/order'),
    'afterpay_order_reference',
    "varchar(255) null"
);
$conn->addColumn(
    $installer->getTable('sales/order'),
    'afterpay_capture_mode',
    "int(1) unsigned null"
);
$conn->addColumn(
    $installer->getTable('sales/order'),
    'afterpay_captured',
    "int(1) unsigned null"
);

$installer->endSetup();