<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 Alinga Web Media Design (http://www.alinga.com.au/)
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */
class Afterpay_Afterpay_Model_System_Config_Source_Paymentreview extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{
    /**
     * Show only statuses mapped to Payment Review state
     * @var string
     */
    protected $_stateStatuses = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
}
