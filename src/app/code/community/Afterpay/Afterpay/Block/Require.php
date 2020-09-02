<?php
/**
 * Class Afterpay_Afterpay_Block_Require
 */
class Afterpay_Afterpay_Block_Require extends Mage_Core_Block_Template
{
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
