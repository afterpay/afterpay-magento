<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */
class Afterpay_Afterpay_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Return statuses
     */
    const RETURN_STATUS_SUCCESS   = "SUCCESS";
    const RETURN_STATUS_CANCELLED = "CANCELLED";
    const RETURN_STATUS_FAILURE   = "FAILURE";

    /**
     * Redirect customer to Afterpay website to complete payment
     */
    public function redirectAction()
    {
        $order = $this->getLastRealOrder();

        try {
            if (!$order->getId()) {
                $this->helper()->log('Payment redirect request: Cannot get order from session, redirecting customer to shopping cart', Zend_Log::WARN);
                $this->_redirect('checkout/cart');
                return;
            }

            $this->helper()->log('Payment redirect request for order ' . $order->getIncrementId(), Zend_Log::INFO);

            $this->loadLayout();

            $payment = $order->getPayment();
            $token   = $payment->getData('afterpay_token');

            /** @var Afterpay_Afterpay_Model_Method_Base $paymentMethod */
            $paymentMethod = $payment->getMethodInstance();

            /** @var Afterpay_Afterpay_Block_Redirect $block */
            $block = $this->getLayout()->getBlock('afterpay.redirect');
            $block->setOrderToken($token);

            // render block with redirecting JavaScript code
            $this->helper()->log('Redirecting customer to Afterpay website... order=' . $order->getIncrementId(), Zend_Log::INFO);
            $this->renderLayout();

        } catch (Mage_Core_Exception $e) {
            // log error and notify customer about incident
            $this->helper()->log('Exception on processing payment redirect request: ' . $e->getMessage(), Zend_Log::ERR);
            Mage::logException($e);
            Mage::getSingleton('checkout/session')->addError($this->__('Afterpay: Error processing payment request.'));

            // re-add all products to shopping cart in case of error
            if ($order->getId()) {
                $order->cancel()->save();
                $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());

                if ($quote->getId()) {
                    $quote->setIsActive(1)->setReservedOrderId(NULL)->save();
                    $this->getCheckoutSession()->replaceQuote($quote);
                }
            }

            $this->_redirect('checkout/cart');
        }

    }

    /**
     * Process payment confirmations / failures / cancellations
     */
    public function returnAction()
    {
        $startTime = microtime(true); // basic profiling

        $order = $this->getLastRealOrder();

        $helper  = $this->helper();
        $request = $this->getRequest();

        try {

            // get request parameters
            $receivedStatus  = trim((string)$request->getParam('status'));
            $receivedToken   = trim((string)$request->getParam('orderToken'));
            $receivedOrderId = trim((string)$request->getParam('orderId'));

            $helper->log(sprintf(
                'Return notification: status=%s orderToken=%s checkoutSessionOrderId=%s ',
                $receivedStatus, $receivedToken, $order->getIncrementId()),
                Zend_Log::DEBUG);


            // check received parameters and order ID from session
            if (!$order->getId()) {
                $this->throwException('No order saved in checkout session');
            } elseif (empty($receivedStatus) || empty($receivedToken)) {
                $this->throwException('Cannot get status and orderToken from session');
            }

            // get payment data from database
            $payment         = $order->getPayment();
            $afterpayToken   = $payment->getData('afterpay_token');
            $afterpayOrderId = $payment->getData('afterpay_order_id');

            // compare received token with saved in DB
            if ($receivedToken !== $afterpayToken) {
                $this->throwException(sprintf(
                    'Order token doesn\'t match database data: orderId=%s receivedToken=%s savedToken=%s',
                    $order->getIncrementId(), $receivedToken, $afterpayToken));
            }

            // check for trial to override order ID
            if (!empty($afterpayOrderId) && !empty($afterpayOrderId) && $receivedOrderId !== $afterpayOrderId) {
                $this->throwException(sprintf(
                    'Trial to override afterpayOrderId: currentOrderId=%s receivedOrderId=%s',
                    $afterpayOrderId, $receivedOrderId));
            }

            if (!empty($receivedOrderId)) {
                /** @var Afterpay_Afterpay_Model_Method_Base $paymentMethod */
                $paymentMethod = $payment->getMethodInstance();
                $paymentMethod->saveAfterpayOrderId($order, $receivedOrderId);

                try {
                    $paymentMethod->createOrderTransaction($order, !in_array($receivedStatus, array(self::RETURN_STATUS_FAILURE, self::RETURN_STATUS_CANCELLED)));
                } catch (Afterpay_Afterpay_Exception $e) {
                    $helper->log('Return notification: ' . $e->getMessage(), Zend_Log::NOTICE);
                }

                $this->processPaymentOrderStatus($receivedStatus, $order);

            } else {
                $this->processPaymentOrderStatus(self::RETURN_STATUS_CANCELLED, $order);
            }

            $order->save();

        } catch (Afterpay_Afterpay_Exception $e) {

            $helper->log('Return notification: ' . $e->getMessage(), Zend_Log::ERR);
            Mage::getSingleton('checkout/session')->addError($this->__('Afterpay: Error processing payment notification.'));

            // return all products to shopping cart and redirect to shopping cart
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)->setReservedOrderId(NULL)->save();
                $this->getCheckoutSession()->replaceQuote($quote);
            }
            $this->_redirect('checkout/cart');

        } catch (Exception $e) {

            $helper->log('Return notification: Exception during processing request: ' . $e->getMessage(), Zend_Log::ERR);
            Mage::logException($e);
            Mage::getSingleton('checkout/session')->addError($this->__('Afterpay: Error processing payment notification.'));
            $this->_redirect('checkout/onepage/failure');

        }

        $endTime = microtime(true);
        $helper->log(
            sprintf(
                "Return notification: Processing took %s ms. Request data:\n%s",
                round(1000 * ($endTime - $startTime)),
                print_r($request->getParams(), true)
            ),
            Zend_Log::DEBUG
        );
    }

    /**
     * Perform changes according to given payment status
     *
     * @param string                 $returnedStatus
     * @param Mage_Sales_Model_Order $order
     */
    protected function processPaymentOrderStatus($returnedStatus, Mage_Sales_Model_Order $order)
    {
        $returnedStatus = strtoupper($returnedStatus);
        $payment        = $order->getPayment();
        $paymentMethod  = $payment->getMethodInstance();
        $logPrefix      = 'Return notification: ';
        $checkoutHelper = Mage::helper('checkout/url');
        $session        = Mage::getSingleton('core/session');

        // retrieve payment status online in case of SUCCESS return notification
        $helper = $this->helper();

        if (self::RETURN_STATUS_SUCCESS == $returnedStatus) {
            if ($order->isPaymentReview()) {
                $helper->log($logPrefix . 'Re-checking order payment status: ' . $order->getIncrementId(), Zend_Log::INFO);
                $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_UPDATE, true);
            } else {
                $helper->log($logPrefix . 'Order status was not re-checked. Order is not in Payment Review state. OrderID=' . $order->getIncrementId(), Zend_Log::NOTICE);
            }
        }

        // process return notification code and order payment status
        if (self::RETURN_STATUS_SUCCESS == $returnedStatus && $order->isPaymentReview()) {

            // order payment is pending review -> send email and just redirect to success
            if (!$order->getEmailSent() && $paymentMethod->getConfigData('order_email')) {
                $order->sendNewOrderEmail()->setEmailSent(true);
            }

            $helper->log($logPrefix . 'Order is in Payment Review status. Redirecting customer to success page. OrderID=' . $order->getIncrementId(), Zend_Log::INFO);
            $this->_redirect('checkout/onepage/success');

        } elseif (self::RETURN_STATUS_SUCCESS == $returnedStatus
            && $order->getState() === Mage_Sales_Model_Order::STATE_PROCESSING
        ) {

            // order payment has been approved -> send email and redirect to success
            if (!$order->getEmailSent() && $paymentMethod->getConfigData('order_email')) {
                $order->sendNewOrderEmail()->setEmailSent(true);
            }

            $helper->log($logPrefix . 'Creating invoice...', Zend_Log::DEBUG);

            try {
                $helper->createInvoice($order);

                $helper->log($helper->__($logPrefix . 'Invoice successfully created'), Zend_Log::DEBUG);
            } catch (Afterpay_Afterpay_Exception $e) {
                $helper->log($helper->__($logPrefix . '%s. Invoice is not created', $e->getMessage()), Zend_Log::INFO);
            } catch (Exception $e) {
                Mage::logException($e);
                $helper->log(
                    $helper->__($logPrefix . 'Invoice creation failed with message: %s', $e->getMessage()),
                    Zend_Log::ERR
                );
            }

            $helper->log($logPrefix . 'Order is in Processing status. Redirecting customer to success page. OrderID=' . $order->getIncrementId(), Zend_Log::INFO);
            $this->_redirect('checkout/onepage/success');

        } elseif (self::RETURN_STATUS_FAILURE == $returnedStatus) {
            // payment failure -> cancel order and redirect to failure page

            $isPendingPayment = $order->getState() === Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;

            if ($isPendingPayment || $order->isPaymentReview()) {
                $helper->log($logPrefix . 'Payment failed. Cancelling order and redirecting to failure page. OrderID=' . $order->getIncrementId(), Zend_Log::INFO);

                $payment->setNotificationResult(true);
                $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_DENY, false);

                $this->returnProductsToCart($order);

            } else {
                $helper->log($logPrefix . 'Payment failed. Redirecting customer to checkout. OrderID=' . $order->getIncrementId(), Zend_Log::INFO);
            }

            $session->addError($helper->__('Your Afterpay payment was declined. Please select an alternative payment method.'));
            $this->_redirectUrl($checkoutHelper->getCheckoutUrl());

        } elseif (self::RETURN_STATUS_CANCELLED == $returnedStatus || $order->isCanceled()) {

            // order has been cancelled -> cancel order and add all products to shopping cart
            $this->cancelOrder($order);
            $this->returnProductsToCart($order);

            $helper->log($logPrefix . 'Order has been cancelled. Redirecting customer to checkout. OrderID=' . $order->getIncrementId(), Zend_Log::INFO);

            $session->addError($helper->__('You have cancelled your Afterpay payment. Please select an alternative payment method.'));
            $this->_redirectUrl($checkoutHelper->getCheckoutUrl());

        } else {
            // TODO: add default redirect for unknown cases + log message about incident
        }
    }

    /**
     * Cancel order
     */
    public function cancelAction()
    {
        $order = $this->getLastRealOrder();

        if ($order && $order->getId()) {
            $this->helper()->log(
                'Requested order cancellation by customer. OrderId: ' . $order->getIncrementId(),
                Zend_Log::DEBUG
            );
            $this->cancelOrder($order);
            $this->returnProductsToCart($order);

            $order->save();
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * Failure action
     */
    public function failureAction()
    {
        $session     = $this->getCheckoutSession();
        $lastQuoteId = $session->getLastQuoteId();
        $lastOrderId = $session->getLastOrderId();

        if (!$lastQuoteId || !$lastOrderId) {
            $this->_redirect('checkout/cart');
            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Get checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current Order model from session
     *
     * @return Mage_Sales_Model_Order
     */
    protected function getLastRealOrder()
    {
        $session = $this->getCheckoutSession();
        $orderId = $session->getLastRealOrderId();
        $order   = Mage::getModel('sales/order');
        if ($orderId) {
            $order->loadByIncrementId($orderId);
        }
        return $order;
    }

    /**
     * @return Afterpay_Afterpay_Helper_Data
     */
    protected function helper()
    {
        return Mage::helper('afterpay');
    }

    /**
     * @param $message
     * @throws Mage_Core_Exception
     * @throws Afterpay_Afterpay_Exception
     */
    protected function throwException($message)
    {
        throw Mage::exception('Afterpay_Afterpay', $message);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return $this
     * @throws Exception
     */
    protected function cancelOrder(Mage_Sales_Model_Order $order)
    {
        if (!$order->isCanceled()) {
            $order
                ->cancel()
                ->addStatusHistoryComment($this->__('Afterpay: Cancelled by customer.'));
        }

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return $this
     */
    protected function returnProductsToCart(Mage_Sales_Model_Order $order)
    {
        // return all products to shopping cart
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());

        if ($quote->getId()) {
            $quote->setIsActive(1)->setReservedOrderId(null)->save();
            $this->getCheckoutSession()->replaceQuote($quote);
        }

        return $this;
    }
}
