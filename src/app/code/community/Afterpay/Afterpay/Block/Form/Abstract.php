<?php

/**
 * 
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */

/**
 * Class Afterpay_Afterpay_Block_Form_Abstract
 * Abstract class for all payment form blocks.
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
