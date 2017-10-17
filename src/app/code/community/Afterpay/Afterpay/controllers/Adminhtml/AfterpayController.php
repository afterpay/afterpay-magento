<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Aferpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au)
 */

/**
 * Class Afterpay_Afterpay_Adminhtml_AfterpayController
 *
 * Handles Admin-side Afterpay Operations 
 */
class Afterpay_Afterpay_Adminhtml_AfterpayController extends Mage_Adminhtml_Controller_Action
{
    public function updateAction()
    {
        $model = new Afterpay_Afterpay_Model_Observer();

        try {
            $model->updateOrderLimits();
            $this->_getSession()->addSuccess(Mage::helper('afterpay')->__('Successfully updated limits'));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirectReferer();
    }

    public function fetchPendingPaymentOrdersInfoAction() {
    	try {
            $model = new Afterpay_Afterpay_Model_Observer();
        	$model->fetchPendingPaymentOrdersInfo(NULL);
    	} catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        $this->_redirectReferer();
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/config/payment');
    }

}
