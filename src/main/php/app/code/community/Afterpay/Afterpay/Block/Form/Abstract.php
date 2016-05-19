<?php

/**
 * Abstract class for all payment form blocks.
 *
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */

/**
 * Class Afterpay_Afterpay_Block_Form_Abstract
 *
 * @method void setRedirectMessage(string $message);
 */
abstract class Afterpay_Afterpay_Block_Form_Abstract extends Mage_Payment_Block_Form
{
    /**
     * Get payment method redirect message
     *
     * @return string
     */
    public function getRedirectMessage()
    {
        if ($this->hasData('redirect_message')) {
            return $this->getData('redirect_message');
        } else {
            return $this->getMethod()->getConfigData('message');
        }
    }
}
