<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */

class Afterpay_Afterpay_Model_Resource_ShippedApiQueue extends Mage_Core_Model_Resource_Db_Abstract
{

    protected function _construct()
    {
        $this->_init('afterpay/afterpay_shipped_api_queue', 'shipped_api_queue_id');
    }

}