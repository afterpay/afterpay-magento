<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 Alinga Web Media Design (http://www.alinga.com.au/)
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */
class Afterpay_Afterpay_Model_Method_Payovertime extends Afterpay_Afterpay_Model_Method_Base
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = 'afterpaypayovertime';

    /**
     * Info and form blocks
     *
     * @var string
     */
    protected $_formBlockType = 'afterpay/form_payovertime';
    protected $_infoBlockType = 'afterpay/info';

    /**
     * Payment type code according to Afterpay API documentation.
     *
     * @var string
     */
    protected $afterPayPaymentTypeCode = 'PBI';
}
