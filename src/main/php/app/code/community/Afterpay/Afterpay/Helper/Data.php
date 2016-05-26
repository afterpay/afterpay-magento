<?php

/**
 * Default Afterpay helper class
 *
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */
class Afterpay_Afterpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var string
     */
    protected $logFileName = 'afterpay.log';

    /**
     * @var bool
     */
    protected $isDebugEnabled;

    /**
     * General logging method
     *
     * @param      $message
     * @param null $level
     */
    public function log($message, $level = null)
    {
        if ($this->isDebugMode() || $level != Zend_Log::DEBUG) {
            Mage::log($message, $level, $this->logFileName);
        }
    }

    /**
     * @return bool
     */
    public function isDebugMode()
    {
        if ($this->isDebugEnabled === null) {
            $this->isDebugEnabled = Mage::getStoreConfigFlag('afterpay/general/debug');
        }

        return $this->isDebugEnabled;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return $this
     * @throws Exception
     * @throws Afterpay_Afterpay_Exception
     */
    public function createInvoice(Mage_Sales_Model_Order $order)
    {
        /** @var Mage_Payment_Model_Method_Abstract $paymentMethod */
        $paymentMethod = $order->getPayment()->getMethodInstance();

        $createInvoice = $paymentMethod->getConfigData('invoice_create', $order->getStoreId());

        if ($createInvoice && $order->getId()) {
            if ($order->hasInvoices()) {
                throw Mage::exception('Afterpay_Afterpay', $this->__('Order already has invoice.'));
            }

            if (!$order->canInvoice()) {
                throw Mage::exception('Afterpay_Afterpay', $this->__("Order can't be invoiced."));
            }

            $invoice = $order->prepareInvoice();

            if ($invoice->getTotalQty() > 0) {
                //Invoice offline because our payment method do not support capture
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);

                if ($order->getPayment()->getLastTransId()) {
                    $invoice->setTransactionId($order->getPayment()->getLastTransId());
                }

                $invoice->register();
                /** @var Mage_Core_Model_Resource_Transaction $transaction */
                $transaction = Mage::getModel('core/resource_transaction');

                $transaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());

                $transaction->save();

                $invoice->addComment($this->__('Afterpay Automatic invoice.'), false);

                // Send invoice email
                if (!$invoice->getEmailSent() && $paymentMethod->getConfigData('invoice_email', $order->getStoreId())) {
                    $invoice->sendEmail()->setEmailSent(true);
                }

                $invoice->save();
            }
        }

        return $this;
    }

    /**
     * Get the current version of the Afterpay extension
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return (string) Mage::getConfig()->getModuleConfig('Afterpay_Afterpay')->version;
    }

    public function calculateInstalment()
    {
        $total = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
        $installment = ceil($total / 4 * 100) / 100;
        return Mage::app()->getStore()->formatPrice($installment, false);
    }
}
