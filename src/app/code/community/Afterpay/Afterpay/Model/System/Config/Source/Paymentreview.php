<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */
class Afterpay_Afterpay_Model_System_Config_Source_Paymentreview extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{
    /**
     * Show only statuses mapped to Payment Review state
     * @var string
     */
    protected $_stateStatuses = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
}
