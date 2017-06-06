<?php
/**
 * Class Afterpay_Afterpay_Block_Require
 */
class Afterpay_Afterpay_Block_Require extends Mage_Core_Block_Template
{
    /**
     * @return array
     */
    public function getRequireJs()
    {
        return array(
            'payovertime' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS) . 'Afterpay/checkout/payovertime.js'
        );
    }

    /**
     * @return array
     */
    public function getRequireStyle()
    {
        return array(
            'payovertime' => $this->getSkinUrl('afterpay/css/afterpay.css'),
        );
    }
}