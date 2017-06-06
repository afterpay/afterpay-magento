<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */
class Afterpay_Afterpay_Block_Adminhtml_System_Config_Form_Field_ModuleVersion extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    public function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return Mage::helper('afterpay')->getModuleVersion();
    }

}
