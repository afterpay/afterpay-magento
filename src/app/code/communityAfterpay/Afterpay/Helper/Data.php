<?php

/**
 * Default Afterpay helper class
 *
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2017 Afterpay (http://www.afterpay.com.au/)
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
    
    /**
     * Calculate The Instalments
     *
     * @return string
     */
    public function calculateInstalment()
    {
        $total = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
        $installment = ceil($total / 4 * 100) / 100;
        return Mage::app()->getStore()->formatPrice($installment, false);
    }

    
    /**
     * Calculate The Total Amount
     *
     * @return string
     */
    public function calculateTotal()
    {
        $total = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
        return $total;
    }
    
    
    //Enterprise Edition Only 
    
    /**
     * Store Credit Manipulations
     *
     * @return bool
     */
    public function storeCreditPlaceOrder()
    {
        //process the Credit Memo on Orders
        if( Mage::getSingleton('checkout/session')->getData('afterpayCustomerBalance') ) {
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);

            $order->setCustomerBalanceAmount( Mage::getSingleton('checkout/session')->getData('afterpayCustomerBalance') );
            $order->setCustomerBalanceInvoiced( Mage::getSingleton('checkout/session')->getData('afterpayCustomerBalance') );
   	    
	    $order->setTotalPaid($order->getGrandTotal());  
	    
            $order->save();
                    
            Mage::getSingleton('checkout/session')->unsetData('afterpayCustomerBalance');
        }

        // probably we are using the default checkout
        return true;
    }

    /**
     * Store Credit Session Set
     *
     * @return void
     */
    public function storeCreditSessionSet($quote) {
        // Utilise Magento Session to preserve Store Credit details
        if( $quote->getCustomerBalanceAmountUsed() ) {
            Mage::getSingleton('checkout/session')->setData('afterpayCustomerBalance', $quote->getCustomerBalanceAmountUsed());
        }
	   
    }
    
    /**
     * Store Credit Session Unset
     *
     * @return void
     */
    public function storeCreditSessionUnset() {
        if( Mage::getSingleton('checkout/session')->getData('afterpayCustomerBalance') ) {
            Mage::getSingleton('checkout/session')->unsetData('afterpayCustomerBalance');
        }
    }


    
    /**
     * Store Credit Session Handler for Capture Payment Phase
     *
     * @return void
     */
    public function storeCreditCapture($quote) {
        if( Mage::getSingleton('checkout/session')->getData('afterpayCustomerBalance') ) {
            
	    $grand_total = $quote->getGrandTotal();
	    $balance = Mage::getSingleton('checkout/session')->getData('afterpayCustomerBalance');
	    
	    $quote->setUseCustomerBalance(1);
            
	    $quote->setCustomerBalanceAmountUsed( $balance );
            //$quote->setBaseCustomerBalanceAmountUsed( $balance );
            
	    if( $grand_total > $balance ) {
	    	$quote->setGrandTotal( $grand_total - $balance );
	    }
	    
	    //$this->getSession()->unsetData('afterpayCustomerBalance');

            return $quote;
        }

        return $quote;
    }
}
