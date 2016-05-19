<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2015 VEN Commerce Ltd (http://www.ven.com)
 */
class Afterpay_Afterpay_Block_Adminhtml_System_Config_Form_Field_ModuleVersion extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    public function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return Mage::helper('afterpay')->getModuleVersion();
    }

}
