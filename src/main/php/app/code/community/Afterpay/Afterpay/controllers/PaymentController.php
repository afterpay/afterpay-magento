<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */

/**
 * Class Afterpay_Afterpay_PaymentController
 *
 * Controller for the entire Payment Process
 * A number of functions here are used across both API Ver 0 and 1 
 */
class Afterpay_Afterpay_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Return statuses
     */
    const RETURN_STATUS_SUCCESS   = "SUCCESS";
    const RETURN_STATUS_CANCELLED = "CANCELLED";
    const RETURN_STATUS_FAILURE   = "FAILURE";

    protected $_quote;

    /**
     * Construct function
     */
    public function _construct()
    {
        parent::_construct();
        $this->_config = 'redirect';
    }

    /**
     * Redirect customer to Afterpay website to complete payment
     */
    public function startAction()
    {
        try {
            // Check with security updated on form key
            if (!$this->_validateFormKey()) {
                Mage::throwException(Mage::helper('afterpay')->__('Detected fraud'));
                return;
            }

            // Load checkout session
            $this->_initCheckout();

            // check if using multi shipping, not supported
            if ($this->_getQuote()->getIsMultiShipping()) {
                Mage::throwException(Mage::helper('afterpay')->__('Afterpay payment is not supported to this checkout'));
            }

            // Redirect if guest is not allowed and use guest
            $quoteCheckoutMethod = $this->_getQuote()->getCheckoutMethod();
            if ($quoteCheckoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST &&
                !Mage::helper('checkout')->isAllowedGuestCheckout(
                    $this->_getQuote(),
                    $this->_getQuote()->getStoreId()
                )) {
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('afterpay')->__('To proceed to Checkout, please log in using your email address.')
                );
                $this->redirectLogin();
            }

            // Perform starting the afterpay transaction
            $token = Mage::getModel('afterpay/order')->start($this->_quote);

            $response = array(
                'success' => true,
                'token'  => $token,
            );

        } catch (Exception $e) {
            // Debug log
            $this->helper()->log($this->__('Error occur during process. %s. QuoteID=%s', $e->getMessage(), $this->_quote->getId()), Zend_Log::ERR);

            // Adding error for redirect and JSON
            $message = Mage::helper('afterpay')->__('There was an error processing your order. %s', $e->getMessage());

            Mage::getSingleton('core/session')->addError($message);
            // Response to the
            $response = array(
                'success'  => false,
                'message'  => $message,
                'redirect' => Mage::getUrl('checkout/cart'),
            );

        }

        // Return the json response to the browser
        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(json_encode($response));
    }

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
     * Return Action checking on configuration in Magento
     */
    public function returnAction()
    {
        // check if using capture then handle new API Ver 1
        if ( Mage::getModel('afterpay/method_payovertime')->isAPIVersion1() ) {
            $this->_capture();
        } else {
            // fall back to using old one
            $this->_order();
        }
    }

    /**
     * Place order to Magento
     */
    public function placeOrderAction()
    {
        try {
            // Load the checkout session
            $this->_initCheckout();

            // Debug log
            $this->helper()->log(
                $this->__(
                    'Creating order in Magento. AfterpayOrderId=%s QuoteID=%s ReservedOrderID=%s',
                    $this->_quote->getAfterpayOrderId(),
                    $this->_quote->getId(),
                    $this->_quote->getReservedOrderId()
                ),
                Zend_Log::NOTICE
            );

            // Placing order using Afterpay
            $placeOrder = Mage::getModel('afterpay/order')->place($this->_quote);

            if ($placeOrder) {

                // Debug log
                $this->helper()->log(
                    $this->__(
                        'Order successfully created. Redirecting to success page. AfterpayOrderId=%s QuoteID=%s ReservedOrderID=%s',
                        $this->_quote->getAfterpayOrderId(),
                        $this->_quote->getId(),
                        $this->_quote->getReservedOrderId()
                    ),
                    Zend_Log::NOTICE
                );
            }

            // Redirect to success page
            $this->_redirect('checkout/onepage/success');
        } catch (Exception $e) {
            // Debug log
            $this->helper()->log(
                $this->__(
                    'Order creation failed. %s. AfterpayOrderId=%s QuoteID=%s ReservedOrderID=%s',
                    $e->getMessage(),
                    $this->_quote->getAfterpayOrderId(),
                    $this->_quote->getId(),
                    $this->_quote->getReservedOrderId()
                ),
                Zend_Log::ERR
            );
            $this->getSession()->addError($e->getMessage());

            // Afterpay redirect
            $this->_checkAndRedirect();
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


    /*------------------------------------------------------------------------------------------------------
                                    Functions used on ALL API Versions 
    ------------------------------------------------------------------------------------------------------*/

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
     * Get core session
     *
     * @return Mage_Core_Model_Session
     */
    protected function getSession()
    {
        return Mage::getSingleton('core/session');
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
     * Get quote of checkout session
     *
     * @return Mage_Sales_Model_Quote
     */
    private function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }


    /**
     * Perform to get the quote
     */
    protected function _initCheckout()
    {
        $quote = $this->_getQuote();
        
        //set up the guest / registered flag
        $quoteCheckoutMethod = $this->_getQuote()->getCheckoutMethod();

        if( $quoteCheckoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST ) {
            $quote->setCustomerIsGuest(1);
            $quote->save();
        }

        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Forbidden');
            Mage::throwException(Mage::helper('paypal')->__('Unable to initialize Afterpay Payment method.'));
        }
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

    /**
     * Redirect to login page
     *
     */
    public function redirectLogin()
    {
        $this->setFlag('', 'no-dispatch', true);
        $this->getResponse()->setRedirect(
            Mage::helper('core/url')->addRequestParam(
                Mage::helper('customer')->getLoginUrl(),
                array('context' => 'checkout')
            )
        );
    }

    /**
     * Afterpay Redirect by using session for overriding
     */
    protected function _checkAndRedirect()
    {
        // Default redirect to checkout if Session afterpay redirect is not exist
        if (!Mage::getSingleton('core/session')->getAfterpayErrorRedirect()) {
            // Redirect to checkout
            $this->_redirectUrl(Mage::helper('checkout/url')->getCheckoutUrl());
        } else {
            // Redirect to cart
            $this->_redirect(Mage::getSingleton('core/session')->getData('afterpay_error_redirect', true));
        }
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
                $order->sendNewOrderEmail();
            }

            $helper->log($logPrefix . 'Order is in Payment Review status. Redirecting customer to success page. OrderID=' . $order->getIncrementId(), Zend_Log::INFO);
            $this->_redirect('checkout/onepage/success');

        } elseif (self::RETURN_STATUS_SUCCESS == $returnedStatus
            && $order->getState() === Mage_Sales_Model_Order::STATE_PROCESSING
        ) {

            // order payment has been approved -> send email and redirect to success
            if (!$order->getEmailSent() && $paymentMethod->getConfigData('order_email')) {
                $order->sendNewOrderEmail();
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


    /*------------------------------------------------------------------------------------------------------
                                    Functions used ONLY on API Version 0 
    ------------------------------------------------------------------------------------------------------*/

    /**
     * Return function if configuration is using order to process order
     * Only used in API V0
     */
    protected function _order()
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


    /*------------------------------------------------------------------------------------------------------
                                    Functions used ONLY on API Version 1
    ------------------------------------------------------------------------------------------------------*/

    /**
     * Process payment confirmations / failures / cancellations for API ver 1
     * Only used in API V1
     */
    protected function _capture()
    {
        try {
            $orderToken = $this->getRequest()->getParam('orderToken');
            $status = $this->getRequest()->getParam('status');
            // $afterpayOrderId = $this->getRequest()->getParam('orderId');

            // Magento finalise the current cart session
            $this->_initCheckout();
            $this->_quote->collectTotals();

            // Check status
            switch ($status) {
                case self::RETURN_STATUS_SUCCESS:
                    /**
                     * SUCCESS => validate, save orderid, create order
                     */
                    $payment = $this->_quote->getPayment();

                    // validate = Check if order token return on the url same as order token has been use on session
                    if ($this->_quote->getPayment()->getAfterpayToken() != $orderToken) {
                        $this->throwException(sprintf(
                            'Order token doesn\'t match database data: orderId=%s receivedToken=%s savedToken=%s',
                            $this->_quote->getReservedOrderId(), $orderToken, $payment->getOrderToken()));
                    }

                    // Debug log
                    $this->helper()->log($this->__('Payment Afterpay succeeded with Afterpay. QuoteID=%s ReservedOrderID=%s',$this->_quote->getId(), $this->_quote->getReservedOrderId()), Zend_Log::NOTICE);

                    // Place order when validation is correct
                    $this->_forward('placeOrder');
                    break;

                case self::RETURN_STATUS_FAILURE:
                    /**
                     * FAILURE => Return the error to the browser
                     */
                    // Debug log
                    $this->helper()->log($this->__('Payment failed. Redirecting customer back to checkout. QuoteID=%s ReservedOrderID=%s', $this->_quote->getId(), $this->_quote->getReservedOrderId()), Zend_Log::NOTICE);

                    // Set error to be shown on browser
                    Mage::throwException(Mage::helper('afterpay')->__('Your Afterpay payment was declined. Please select an alternative payment method.'));
                    break;

                case self::RETURN_STATUS_CANCELLED:
                    /**
                     * CANCELLED => Return the error to the browser
                     */
                    // Debug log
                    $this->helper()->log($this->__('Afterpay status is cancelled. Redirecting customer back to checkout. QuoteID=%s ReservedOrderID=%s', $this->_quote->getId(), $this->_quote->getReservedOrderId()), Zend_Log::NOTICE);

                    // Set error to be shown on browser
                    Mage::throwException(Mage::helper('afterpay')->__('You have cancelled your Afterpay payment. Please select an alternative payment method.'));
                    break;

                default:
                    /**
                     * OTHER => Return the error to the browser
                     */
                    // Debug log
                    $this->helper()->log($this->__('Order has been cancelled. Redirecting customer to checkout. QuoteID=%s ReservedOrderID=%s', $this->_quote->getId(), $this->_quote->getReservedOrderId()), Zend_Log::NOTICE);

                    // Set error to be shown on browser
                    Mage::throwException(Mage::helper('afterpay')->__('There was an error processing your order.'));
                    break;
            }
        } catch (Exception $e) {
            // Add error message
            $this->getSession()->addError($e->getMessage());

            // Afterpay redirect
            $this->_checkAndRedirect();
        }

    }
}
