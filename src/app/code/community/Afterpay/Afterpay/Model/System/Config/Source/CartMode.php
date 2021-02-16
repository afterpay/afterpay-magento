<?php

class Afterpay_Afterpay_Model_System_Config_Source_CartMode
{
    const MAGENTO = 1;
    const EXPRESS = 2;
    const NO = 0;

    /**
     * Convert to option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            self::MAGENTO => 'Yes - Magento Checkout',
            self::EXPRESS => 'Yes - Afterpay Express Checkout',
            self::NO => 'No',
        );
    }
}
