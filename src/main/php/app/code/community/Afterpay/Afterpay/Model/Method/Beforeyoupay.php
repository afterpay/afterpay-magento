<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 Alinga Web Media Design (http://www.alinga.com.au/)
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 * @deprecated
 */
class Afterpay_Afterpay_Model_Method_Beforeyoupay extends Afterpay_Afterpay_Model_Method_Base
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = 'afterpaybeforeyoupay';

    /**
     * Info and form blocks
     *
     * @var string
     */
    protected $_formBlockType = 'afterpay/form_beforeyoupay';
    protected $_infoBlockType = 'afterpay/info';

    /**
     * Payment type code according to Afterpay API documentation.
     *
     * @var string
     */
    protected $afterPayPaymentTypeCode = 'PAD';

    /**
     * Check whether payment method can be used
     *
     * Hard code to be unavailable. This model is remaining in place to handle any existing orders placed with this
     * method, but we want to be sure we won't place new orders with it.
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return false;
    }
}
