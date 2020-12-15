<?php

class Afterpay_Afterpay_Block_Onetouch extends Mage_Core_Block_Template
{
    /**
     * Render the block
     *
     * @return string
     */
    protected function _toHtml()
    {
        $total = $this->getTotalAmount();
        if  ( Mage::getStoreConfigFlag('afterpay/payovertime_cart/show_onetouch')
                && Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Afterpay_Model_Method_Base::API_ENABLED_FIELD)
                && Mage::helper('afterpay/checkout')->noConflict()
                && Mage::getModel('afterpay/method_payovertime')->canUseForCheckoutSession()
                && $total > 0
                && $total >= Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Afterpay_Model_Method_Base::API_MIN_ORDER_TOTAL_FIELD)
                && $total <= Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Afterpay_Model_Method_Base::API_MAX_ORDER_TOTAL_FIELD)
            ) {
            return parent::_toHtml();
        } else {
            return '';
        }
    }

    /**
     * Calculate how much each instalment will cost
     *
     * @return string
     */
    public function getInstalmentAmount()
    {
        return Mage::helper('afterpay')->calculateInstalment();
    }

    /**
     * Calculate the final value of the transaction
     *
     * @return string
     */
    public function getTotalAmount()
    {
        return Mage::helper('afterpay')->calculateTotal();
    }
}
