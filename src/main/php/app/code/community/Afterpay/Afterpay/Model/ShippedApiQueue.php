<?php
/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2015 VEN Commerce Ltd (http://www.ven.com)
 */

/**
 * Class Afterpay_Afterpay_Model_ShippedApiQueue
 *
 * @method Afterpay_Afterpay_Model_ShippedApiQueue setPaymentId(int $errorsCount)
 * @method int getPaymentId()
 * @method Afterpay_Afterpay_Model_ShippedApiQueue setTrackingNumber(string $trackingNumber)
 * @method string getTrackingNumber()
 * @method Afterpay_Afterpay_Model_ShippedApiQueue setCourier(string $trackingNumber)
 * @method string getCourier()
 * @method Afterpay_Afterpay_Model_ShippedApiQueue setErrorsCount(int $errorsCount)
 * @method int getErrorsCount()
 */
class Afterpay_Afterpay_Model_ShippedApiQueue extends Mage_Core_Model_Abstract
{

    protected function _construct()
    {
        $this->_init('afterpay/shippedApiQueue');
    }

}