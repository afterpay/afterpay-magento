<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */
class Afterpay_Afterpay_Block_Info extends Mage_Payment_Block_Info
{
    /**
     * Prepare information specific to current payment method
     *
     * @param Varien_Object|array $transport
     * @return Varien_Object
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }

        $transport = parent::_prepareSpecificInformation($transport);
        $helper    = Mage::helper('afterpay');

        if (!$this->getIsSecureMode()) {

            /** @var Mage_Sales_Model_Order_Payment $info */
            $info  = $this->getInfo();
            $txnId = $info->getLastTransId();

            if (!$txnId) { // if order doesn't have transaction (for instance: Pending Payment orders)

                $transport->addData(array($helper->__('Order ID') => $helper->__('(none)')));
                $token = $info->getData('afterpay_token');
                $transport->addData(array($helper->__('Order Token') => $token ? $token : $helper->__('(none)')));

            } else { // if order already has transaction

                $transport->addData(array($helper->__('Order ID') => $txnId));

                $lastTxn    = $info->getTransaction($txnId);
                $rawDetails = $lastTxn instanceof Mage_Sales_Model_Order_Payment_Transaction ?
                    $lastTxn->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS) : false;

                if (is_array($rawDetails)) {
                    if (isset($rawDetails['paymentType'])) {
                        $transport->addData(array($helper->__('Payment Type') => $rawDetails['paymentType']));
                    }
                    if (isset($rawDetails['status'])) {
                        $transport->addData(array($helper->__('Payment Status') => $rawDetails['status']));
                    }
                    if (isset($rawDetails['consumerName'])) {
                        $transport->addData(array($helper->__('Consumer Name') => $rawDetails['consumerName']));
                    }
                    if (isset($rawDetails['consumerEmail'])) {
                        $transport->addData(array($helper->__('Consumer Email') => $rawDetails['consumerEmail']));
                    }
                    if (isset($rawDetails['consumerTelephone'])) {
                        $transport->addData(array($helper->__('Consumer Tel.') => $rawDetails['consumerTelephone']));
                    }
                }

            }
        }

        return $transport;
    }
}