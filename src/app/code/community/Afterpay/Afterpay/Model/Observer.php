<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */
class Afterpay_Afterpay_Model_Observer
{
    /* Limit of orders which can be processed in one cron job run */
    const ORDERS_PROCESSING_LIMIT = 50;

    /* Limit of requests which will processed in one cron job run */
    const SET_SHIPPED_API_REQUESTS_LIMIT = 50;

    /* Limit of errors count before track ID is deleted from processing queue */
    const SET_SHIPPED_API_QUEUE_ERRORS_LIMIT = 3;

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string $comment
     * @return boolean
     */
    private function orderHasHistoryComment(Mage_Sales_Model_Order $order, $comment)
    {
        /** @var $history Mage_Sales_Model_Order_Status_History */
        foreach ($order->getAllStatusHistory() as $history) {
            if ($history->getComment() === $comment) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Afterpay_Afterpay_Helper_Data
     */
    protected function helper()
    {
        return Mage::helper('afterpay');
    }

    /**
     * Get a database connection
     *
     * @return Varien_Db_Adapter_Interface
     */
    public function getConnection()
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    /**
     * Set a system configuration value
     *
     * This method is a copy of the Mage_Core_Model_Resource_Setup method, that can't really be called directly
     *
     * @param string    $path       Path to system config value
     * @param mixed     $value      Value to set
     * @param string    $scope      Scope level to delete
     * @param int       $scopeId    Scope ID to delete
     * @return $this
     */
    protected function _setConfigData($path, $value, $scope = 'default', $scopeId = 0)
    {
        $table = Mage::getSingleton('core/resource')->getTableName('core/config_data');
        // this is a fix for mysql 4.1
        $this->getConnection()->showTableStatus($table);

        $data  = array(
            'scope'     => $scope,
            'scope_id'  => $scopeId,
            'path'      => $path,
            'value'     => $value
        );
        $this->getConnection()->insertOnDuplicate($table, $data, array('value'));
        return $this;
    }

    /**
     * Delete a system configuration value
     *
     * This method is a copy of the Mage_Core_Model_Resource_Setup method, that can't really be called directly
     *
     * @param string $path  Path to system config value
     * @param string $scope Configuration scope
     * @return $this
     */
    protected function _deleteConfigData($path, $scope = null)
    {
        $where = array('path = ?' => $path);
        if (!is_null($scope)) {
            $where['scope = ?'] = $scope;
        }
        $this->getConnection()->delete(Mage::getSingleton('core/resource')->getTableName('core/config_data'), $where);
        return $this;
    }

    /**
     * Update the order limit values for the Afterpay payment methods
     *
     * Note that in order to fully set configuration values, we clear the config cache after this method runs
     *
     * @param mixed $observer This is unused and is here for compatibility with the Magento event system
     * @throws Mage_Core_Exception
     */
    public function updateOrderLimits($observer = null)
    {
        $configs = array(
            'PAY_BY_INSTALLMENT'    => 'afterpaypayovertime',
        );

        $website_param = Mage::app()->getRequest()->getParam('website');

        foreach ($configs as $tla => $payment) {

            $base = new Afterpay_Afterpay_Model_Method_Payovertime();

            if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) // website level
            {

                $website_code = Mage::getSingleton('adminhtml/config_data')->getWebsite();
                $website_id = Mage::getModel('core/website')->load($website_code)->getId();

                if (!Mage::app()->getWebsite($website_id)->getConfig('payment/' . $payment . '/active')) {
                    continue;
                }

                $overrides = array('website_id' => $website_id);
                $level = 'websites';
                $target_id = $website_id;
            }
            else if( !empty( $website_param ) ) {

                $website_id = $website_param;

                if (!Mage::app()->getWebsite($website_id)->getConfig('payment/' . $payment . '/active')) {
                    continue;
                }

                $overrides = array('website_id' => $website_id);
                $level = 'websites';
                $target_id = $website_id;
            }
            else // default level
            {
                $target_id = 0;
                $level = 'default';

                if (!Mage::getStoreConfigFlag('payment/' . $payment . '/active')) {
                    continue;
                }

                $website_code = Mage::getSingleton('adminhtml/config_data')->getWebsite();
                $overrides = array();
            }

            $values = $base->getPaymentAmounts($payment, $tla, $overrides);

            //skip if there is no values
            if( !$values ) {
                continue;
            }

            $this->_doPaymentLimitUpdate($payment, $values, $level, $target_id);
        }

        // after changing system configuration, we need to clear the config cache
        Mage::app()->cleanCache(array(Mage_Core_Model_Config::CACHE_TAG));
    }

    private function _doPaymentLimitUpdate($payment, $values, $level, $target_id)
    {
        if (!isset($values['minimumAmount'])) {
            $values['minimumAmount'] = ['amount' => 0];
        }
        $this->_setConfigData('payment/'.$payment.'/min_order_total', $values['minimumAmount']['amount'], $level, $target_id);

        if (!isset($values['maximumAmount'])) {
            $values['maximumAmount'] = ['amount' => 0];
        }
        $this->_setConfigData('payment/'.$payment.'/max_order_total', $values['maximumAmount']['amount'], $level, $target_id);
    }

    /**
     * Add a warning message to show that Magento logging is disabled
     *
     * @param mixed $observer
     */
    public function addLogWarningMessage($observer)
    {
        $params = Mage::app()->getRequest()->getParams();

        // if we are editing the afterpay section of the system configuration
        if (isset ($params['section']) && $params['section'] == 'afterpay') {
            if (Mage::getStoreConfigFlag('afterpay/general/debug') &&
               !Mage::getStoreConfigFlag('dev/log/active')) {
                // Afterpay debug process is enabled, but magento core logging is disabled
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('afterpay')->__('Afterpay logging is enabled however core Magento logging is disabled, so Afterpay debug logs will not be written. Please turn on Magento logging at Developer -> Log Settings.')
                );
            }

            if (Mage::helper('afterpay/checkout')->isUsingUnsupportedCheckout()) {
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('afterpay')->__('You appear to be using a checkout extension not fully supported by Afterpay. The Afterpay popup will be displayed over a new, empty, page.')
                );
            }
        }
    }

    /**
     * Set the afterpay order token as part of the order body response
     *
     * @param $observer
     * @return $this
     */
    public function addTokenToOrderResponse($observer)
    {
	    $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
        if ($order instanceof Mage_Sales_Model_Order) {
            $payment = $order->getPayment();
            if ($payment instanceof Mage_Payment_Model_Info && $payment->getMethodInstance() instanceof Afterpay_Afterpay_Model_Method_Base) {
                $response = Mage::app()->getResponse();
                $helper = Mage::helper('core');
                $responseBody = $helper->jsonDecode($response->getBody());

                $afterpayToken = $payment->getData('afterpay_token');

                $responseBody['afterpayToken'] = $afterpayToken;
                $response->setBody($helper->jsonEncode($responseBody));

                $this->helper()->log('Setting afterpay token to order (' . $order->getIncrementId() . ') response : ' . $afterpayToken, Zend_Log::DEBUG);
            }
        }
        return $this;
    }

    /**
     * Adding extra layout handle (i.e <default_handle>_MODULE_<module_name_case_sensitive>)
     *
     * Due to some other checkout extension will use the same handle but different methodologies.
     * Example: Idev_OneStepCheckout and MageStore_Onestepcheckout
     *
     * @param $observer
     */
    public function addModuleToHandle($observer)
    {
        // Get request
        $request = Mage::app()->getRequest();

        // Applied for only url onestepcheckout/index/index
        if ($request->getModuleName() == 'onestepcheckout' &&
            $request->getControllerName() == 'index' &&
            $request->getActionName() == 'index') {

            /* @var $update Mage_Core_Model_Layout_Update */
            $update = $observer->getEvent()->getLayout()->getUpdate();
            $update->addHandle('onestepcheckout_index_index_MODULE_' . $request->getControllerModule());
        }
    }
}
