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
 
 class Afterpay_Afterpay_Model_Sources_TaxClasses
{
    public function toOptionArray()
    {
        $collection = Mage::getModel('tax/class')->getCollection()
                                                 ->distinct(true)
                                                 ->addFieldToFilter(
                                                     'class_type',
                                                     array(
                                                         'like' => 'PRODUCT'
                                                     )
                                                 )
                                                 ->load();
        
        $classes = $collection->getColumnValues('class_id');
        
        $optionArray = array();
        $optionArray[''] = array('value' => '', 'label' => Mage::helper('afterpay')->__('None'));
        foreach ($classes as $class) {
            if (empty($class)) {
                continue;
            }
            $optionArray[$class] = array('value' => $class, 'label' => Mage::getModel('tax/class')->load($class)->getClassName());
        }
       
        return $optionArray;
    }
}