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
    protected $_checkout_triggered;

    /**
     * Construct function
     */
    public function _construct()
    {
        parent::_construct();
        $this->_config = 'redirect';
        $this->_checkout_triggered = false;
    }

    /**
     * Redirect customer to Afterpay website to complete payment
     */
    public function startAction()
    {
        $this->_checkout_triggered = true;

        try {
	       /**
	       * In some checkout extension the post data used rather than cart session
	       *
	       * Adding post data to put in cart session
	       */
            $params = Mage::app()->getRequest()->getParams();
            if ($params) {
                $this->_saveCart($params);
            }
            
            // Check with security updated on form key
            if (!$this->_validateFormKey()) {

                $frontend_form_key  =   Mage::app()->getRequest()->getParam('form_key');
                $session_form_key   =   Mage::getSingleton('core/session')->getFormKey();

                $this->helper()->log('Detected fraud. Front-End Key:' . $frontend_form_key . ' Session Key:' . $session_form_key, Zend_Log::ERR);

                Mage::throwException(Mage::helper('afterpay')->__('Detected fraud.'));
                return;
            }

            // Load checkout session
            $this->_initCheckout();

            // check if using multi shipping, not supported
            if ($this->_quote->getIsMultiShipping()) {
                Mage::throwException(Mage::helper('afterpay')->__('Afterpay payment is not supported to this checkout'));
            }

            $this->userProcessing($this->_quote, $this->getRequest() );

            // Redirect if guest is not allowed and use guest
            // $quoteCheckoutMethod = $this->_quote->getCheckoutMethod();
            $quoteCheckoutMethod = $this->getCheckoutMethod(); //Paypal Express Style
            if ($quoteCheckoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST &&
                !Mage::helper('checkout')->isAllowedGuestCheckout(
                    $this->_quote,
                    $this->_quote->getStoreId()
                )) {
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('afterpay')->__('To proceed to Checkout, please log in using your email address.')
                );
                $this->redirectLogin();
            }

            // Utilise Magento Session to preserve Store Credit details
    	    if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
    	    	$this->_quote = $this->helper()->storeCreditSessionSet($this->_quote);
    	    	$this->_quote = $this->helper()->giftCardsSessionSet($this->_quote);
    	    }

            // Perform starting the afterpay transaction
            $token = Mage::getModel('afterpay/order')->start($this->_quote);
            $response = array(
                'success' => true,
                'token'  => $token,
            );

        } catch (Exception $e) {
            // Debug log
            if( empty($this->_quote) ) {
                $this->helper()->log($this->__('Error occur during process, Quote not found. %s.', $e->getMessage(), Zend_Log::ERR));
            }
            else {
                $this->helper()->log($this->__('Error occur during process. %s. QuoteID=%s', $e->getMessage(), $this->_quote->getId()), Zend_Log::ERR);
            }
            
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
                    $this->_quote->getData('afterpay_order_id'),
                    $this->_quote->getId(),
                    $this->_quote->getReservedOrderId()
                ),
                Zend_Log::NOTICE
            );


            $checkout_method = $this->_quote->getCheckoutMethod();
            if( $checkout_method == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER ) {
                $this->_prepareNewAfterpayCustomerQuote();
            }
            else if( $checkout_method == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST ) {
                $this->_prepareAfterpayGuestQuote();
            }
            else {
                $this->_prepareAfterpayCustomerQuote();
            }

            // Placing order using Afterpay
            $placeOrder = Mage::getModel('afterpay/order')->place($this->_quote);

            if ($placeOrder) {
	    
        	    //process the Store Credit on Orders
                if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
            		$this->helper()->storeCreditPlaceOrder();
            		$this->helper()->giftCardsPlaceOrder();
            	}


                // Debug log
                $this->helper()->log(
                    $this->__(
                        'Order successfully created. Redirecting to success page. AfterpayOrderId=%s QuoteID=%s ReservedOrderID=%s',
                        $this->_quote->getData('afterpay_order_id'),
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
                    'Order creation failed. %s. AfterpayOrderId=%s QuoteID=%s ReservedOrderID=%s Stack Trace=%s',
                    $e->getMessage(),
                    $this->_quote->getData('afterpay_order_id'),
                    $this->_quote->getId(),
                    $this->_quote->getReservedOrderId(),
		    $e->getTraceAsString()
                ),
                Zend_Log::ERR
            );
            $this->getSession()->addError($e->getMessage());
            $this->_quote->getPayment()->setData('afterpay_token', NULL)->save();

            // Afterpay redirect
            $this->_checkAndRedirect();
        }
    }

    /**
     * Get checkout method
     *
     * @return string
     */
    public function getCheckoutMethod()
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            return Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER;
        }
        if (!$this->_quote->getCheckoutMethod()) {
            if (Mage::helper('checkout')->isAllowedGuestCheckout($this->_quote)) {
                $this->_quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
            } else {
                $this->_quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
            }
        }
        return $this->_quote->getCheckoutMethod();
    }



    /**
     * Handles quote for guest checkout
     *
     */
    protected function _prepareAfterpayGuestQuote()
    {
        $quote = $this->_quote;
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    }

    /**
     * Handles quote for registered customer
     *
     */
    protected function _prepareAfterpayCustomerQuote()
    {
        $quote      = $this->_quote;
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

        $customer = $quote->getCustomer();
        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }
        if ($shipping && ((!$shipping->getCustomerId() && !$shipping->getSameAsBilling())
            || (!$shipping->getSameAsBilling() && $shipping->getSaveInAddressBook()))) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }
        if ($shipping && isset($customerBilling) && !$customer->getDefaultShipping() && $shipping->getSameAsBilling()) {
            $customerBilling->setIsDefaultShipping(true);
        } elseif ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        }
        $quote->setCustomer($customer)->setCustomerIsGuest(false);
    }

    /**
     *
     * Handles customer quote creation for registering customers 
     *
     */
    protected function _prepareNewAfterpayCustomerQuote()
    {
        $quote      = $this->_quote;
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

        $customer = $this->_lookupCustomer();

        if ($customer->getData()) {
            // $quote->loginById($customerId);

            $session = Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
            return $this->_prepareAfterpayCustomerQuote();
        }

        $customer = $quote->getCustomer();
        /** @var $customer Mage_Customer_Model_Customer */
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } elseif ($shipping) {
            $customerBilling->setIsDefaultShipping(true);
        }
        /**
         * @todo integration with dynamica attributes customer_dob, customer_taxvat, customer_gender
         */
        if ($quote->getCustomerDob() && !$billing->getCustomerDob()) {
            $billing->setCustomerDob($quote->getCustomerDob());
        }

        if ($quote->getCustomerTaxvat() && !$billing->getCustomerTaxvat()) {
            $billing->setCustomerTaxvat($quote->getCustomerTaxvat());
        }

        if ($quote->getCustomerGender() && !$billing->getCustomerGender()) {
            $billing->setCustomerGender($quote->getCustomerGender());
        }

        Mage::helper('core')->copyFieldset('checkout_onepage_billing', 'to_customer', $billing, $customer);

        $email = $quote->getCustomerEmail();
        $password = $customer->decryptPassword($quote->getPasswordHash());

        $customer->setEmail($email);
        $customer->setPrefix($quote->getCustomerPrefix());
        $customer->setFirstname($quote->getCustomerFirstname());
        $customer->setMiddlename($quote->getCustomerMiddlename());
        $customer->setLastname($quote->getCustomerLastname());
        $customer->setSuffix($quote->getCustomerSuffix());
        $customer->setPassword($password);
        $customer->setPasswordHash($customer->hashPassword($customer->getPassword()));
        $customer->save();

        //force login
        $session = Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
        $session->login($email, $password);

        $quote->setCustomer($customer)->setCustomerIsGuest(false);
    }

    /**
     * Fins the customer ID
     *
     * @return int
     */
    protected function _lookupCustomer()
    {
        return Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($this->_quote->getCustomerEmail());
    }

    /**
     * Cancel order
     */
    public function cancelAction()
    {
        // If we are using API version 1, an order won't have been created so we shouldn't attempt to cancel anything
        // Unless it is Enterprise Edition, we need to make sure the Store Credit Session is unset
        if ( Mage::getModel('afterpay/method_payovertime')->isAPIVersion1() ) {

            if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
    	    	$this->helper()->storeCreditSessionUnset();
    	    	$this->helper()->giftCardsSessionUnset();
    	    }

            $this->_getQuote()->getPayment()->setData('afterpay_token', NULL)->save();
	
            $this->_redirect('checkout/cart');
            return;
        }

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

            if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
                $this->helper()->storeCreditSessionUnset();
                $this->helper()->giftCardsSessionUnset();
            }

            $this->_redirect('checkout/cart');
            $this->_getQuote()->getPayment()->setData('afterpay_token', NULL)->save();

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

        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Forbidden');

            if( !$quote->hasItems() ) {
                $message = 'Missing items from Quote';
                $this->helper()->log(
                    $message,
                    Zend_Log::DEBUG
                );
            }
            else if( $quote->getHasError() ) {
                $message = 'Quote Error Detected: ' . $quote->getMessage();
                $this->helper()->log(
                    $message,
                    Zend_Log::DEBUG
                );
            }

            Mage::throwException(Mage::helper('afterpay')->__('Unable to initialize Afterpay Payment method: ' . $message));
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
        if (!Mage::getSingleton('core/session')->getData('afterpay_error_redirect')) {
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
	    
    	    if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
    	    	$this->_quote = $this->helper()->storeCreditCapture($this->_quote); 
		        $this->_quote->save();
    	    }
	    

            // Check status
            switch ($status) {
                case self::RETURN_STATUS_SUCCESS:
                    /**
                     * SUCCESS => validate, save orderid, create order
                     */
    	    	     
        	     //Gift Card handling needs to be here to avoid the reversal problems 
        	     if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
        	     	$this->_quote = $this->helper()->giftCardsCapture($this->_quote); 
                        $this->_quote->save();
        	     }
		
		
                    $payment = $this->_quote->getPayment();

                    // validate = Check if order token return on the url same as order token has been use on session
                    if ($this->_quote->getPayment()->getData('afterpay_token') != $orderToken && $this->_checkout_triggered) {
                        $this->throwException(sprintf(
                            'Warning: Order token doesn\'t match database data: orderId=%s receivedToken=%s savedToken=%s',
                            $this->_quote->getReservedOrderId(), $orderToken, $payment->getOrderToken()));
                    }
                    else if( !$this->_checkout_triggered ) {
                        $this->_quote->getPayment()->setData('afterpay_token', $orderToken);
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

                    $this->_quote->getPayment()->setData('afterpay_token', NULL)->save();

                    // Set error to be shown on browser
                    Mage::throwException(Mage::helper('afterpay')->__('Your Afterpay payment was declined. Please select an alternative payment method.'));
                    break;

                case self::RETURN_STATUS_CANCELLED:
                    /**
                     * CANCELLED => Return the error to the browser
                     */
                    // Debug log
                    $this->helper()->log($this->__('Afterpay status is cancelled. Redirecting customer back to checkout. QuoteID=%s ReservedOrderID=%s', $this->_quote->getId(), $this->_quote->getReservedOrderId()), Zend_Log::NOTICE);

                    $this->_quote->getPayment()->setData('afterpay_token', NULL)->save();

                    // Set error to be shown on browser
                    Mage::throwException(Mage::helper('afterpay')->__('You have cancelled your Afterpay payment. Please select an alternative payment method.'));
                    break;

                default:
                    /**
                     * OTHER => Return the error to the browser
                     */
                    // Debug log
                    $this->helper()->log($this->__('Order has been cancelled. Redirecting customer to checkout. QuoteID=%s ReservedOrderID=%s', $this->_quote->getId(), $this->_quote->getReservedOrderId()), Zend_Log::NOTICE);

                    $this->_quote->getPayment()->setData('afterpay_token', NULL)->save();

                    // Set error to be shown on browser
                    Mage::throwException(Mage::helper('afterpay')->__('There was an error processing your order.'));
                    break;
            }
        } catch (Exception $e) {
            // Add error message
            $this->getSession()->addError($e->getMessage());

            $this->helper()->log($this->__('Exception during order creation. %s', $e->getMessage()), Zend_Log::ERR);

            // Afterpay redirect
            $this->_checkAndRedirect();
        }

    }

    /**
     * Handle AJAX calls for Customer's Checkout Method setup
     * This is to handle OneStepCheckouts that only set the Checkout Methods upon Order Place
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param Mage_Core_Controller_Request $request
     */
    public function userProcessing($quote, $request)
    {
        $logged_in = Mage::getSingleton('customer/session')->isLoggedIn();
        $create_account = $request->getParam("create_account");
	
	    if( !is_null($this->getCheckoutMethod()) && ( empty($create_account) ) ) {
            return;
        }

        try {

            if( $create_account ) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
            }
            else if( !$create_account && !$logged_in ) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
            }
            else {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER);
            }

            $quote->save();
	      
        }
        catch (Exception $e) {
            // Add error message
            $this->getSession()->addError($e->getMessage());
        }
    }


    /**
     *
     * The fallback functionality which creates a page that handle the creation of new token
     * Then, force a redirect to Afterpay gateway to continue processing the order as normal
     *
     */
    public function redirectFallbackAction() {

        $this->_initCheckout();

        $token = Mage::getModel('afterpay/order')->start($this->_quote);

        $payment = $this->_quote->getPayment();
        $payment->setData('afterpay_token', $token);
        $payment->save();

        $this->_quote->setPayment($payment);
        $this->_quote->save();

        // Mage::getSingleton("checkout/session")->setQuote($quote);
        $target_url = Mage::getModel('afterpay/order')->getApiAdapter()->getApiRouter()->getGatewayApiUrl($token);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $result['redirect'] = $target_url;
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
        else {
                    
            $this->getResponse()->setRedirect($target_url);
            $this->getResponse()->sendResponse();
            exit;
        }
    }
    
    

    /**
     * Save Cart data from post request
     *
     * @param $array
     */
    protected function _saveCart($array)
    {
        $skipShipping = false;
        $request = Mage::app()->getRequest();
        foreach ($array as $type => $data) {
            $result = array();
            switch ($type) {
                case 'billing':
                    $result = Mage::getModel('checkout/type_onepage')->saveBilling($data, $request->getPost('billing_address_id', false));
                    $skipShipping = array_key_exists('use_for_shipping', $data) && $data['use_for_shipping'] ? true : false;
                    break;
                case 'shipping':
                    if (!$skipShipping) {
                        $result = Mage::getModel('checkout/type_onepage')->saveShipping($data, $request->getPost('shipping_address_id', false));
                    }
                    break;
                case 'shipping_method':
                    $result = Mage::getModel('checkout/type_onepage')->saveShippingMethod($data);
                    break;
                case 'payment':
                    $result = Mage::getModel('checkout/type_onepage')->savePayment(array('method' => Afterpay_Afterpay_Model_Method_Payovertime::CODE));
                    break;
            }

            if (array_key_exists('error', $result) && $result['error'] == 1) {
                Mage::throwException(Mage::helper('afterpay')->__('%s', json_encode($result['message'])));
            }
        }
    }
}