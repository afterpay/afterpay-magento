<?php

class Afterpay_Afterpay_Block_Adminhtml_System_Config_Form_Field_Update_Limits extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        if (!$this->getTemplate()) {
            $this->setTemplate('afterpay/limits.phtml');
        }
    }

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $element->unsScope()
            ->unsCanUseWebsiteValue()
            ->unsCanUseDefaultValue();

        return parent::render($element);
    }

    public function _getElementHtml(Varien_Data_Form_Element_Abstract $elemen)
    {
        return $this->_toHtml();
    }
}