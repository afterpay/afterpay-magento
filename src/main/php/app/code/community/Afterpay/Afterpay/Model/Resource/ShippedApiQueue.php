<?php
/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2015 VEN Commerce Ltd (http://www.ven.com)
 */

class Afterpay_Afterpay_Model_Resource_ShippedApiQueue extends Mage_Core_Model_Resource_Db_Abstract
{

    protected function _construct()
    {
        $this->_init('afterpay/afterpay_shipped_api_queue', 'shipped_api_queue_id');
    }

}