<?php

class Afterpay_Afterpay_Model_System_Config_Source_RedirectMode
{
    const REDIRECT = 'redirect';
    const LIGHTBOX = 'lightbox';

    /**
     * Convert to option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            self::REDIRECT  => 'Redirect',
            self::LIGHTBOX   => 'Lightbox',
        );
    }
}
