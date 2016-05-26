<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 Alinga Web Media Design (http://www.alinga.com.au/)
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */
class Afterpay_Afterpay_Block_Form_Beforeyoupay extends Afterpay_Afterpay_Block_Form_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('afterpay/form/beforeyoupay.phtml');
    }
}
