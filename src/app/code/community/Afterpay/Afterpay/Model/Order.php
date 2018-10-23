<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

class Afterpay_Afterpay_Model_Order extends Afterpay_Afterpay_Model_Method_Payovertime
{
    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Start creating order for Afterpay
     *
     * @param $quote
     *
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function start($quote)
    {

        // Magento calculate the totals
        $quote->collectTotals();

        // Check if total is 0 and Afterpay won't processing it
        if (!$quote->getGrandTotal() && !$quote->hasNominalItems()) {
            Mage::throwException(Mage::helper('afterpay')->__('Afterpay does not support processing orders with zero amount. To complete your purchase, proceed to the standard checkout process.'));
        }

        // Reserved order Id and save it to quote
        $quote->reserveOrderId()->save();

        // Afterpay build order token request - accommodate both Ver 0 and 1
        $postData = $this->getApiAdapter()->buildOrderTokenRequest($quote, array('merchantOrderId' => $quote->getReservedOrderId()), $this->afterPayPaymentTypeCode);
      
        $gatewayUrl = $this->getApiAdapter()->getApiRouter()->getOrdersApiUrl();

        // Request order token to API
        $result = $this->_sendRequest($gatewayUrl, $postData, Zend_Http_Client::POST, 'StartAfterpayPayment');
        $resultObject = json_decode($result->getBody());

        // Check if token is NOT in response
        if ( empty($resultObject->orderToken) && empty($resultObject->token) ) {
            throw Mage::exception('Afterpay_Afterpay', 'Afterpay API Gateway Error.');
        } else {
            // Save token to the sales_flat_quote_payment

            //API Ver 0
            if( !empty($resultObject->orderToken) ) {
                $orderToken = $resultObject->orderToken;
            }
            else if( !empty($resultObject->token) ) {
                $orderToken = $resultObject->token;
            }

            try {
                $payment = $quote->getPayment();
                $payment->setData('afterpay_token', $orderToken);
                $payment->save();

                // Added to log
                Mage::helper('afterpay')->log(
                    sprintf('Token successfully saved for reserved order %s. token=%s', $quote->getReservedOrderId(), $orderToken),
                    Zend_Log::NOTICE
                );
            }
            catch (Exception $e) {
                // Add error message
                $message = 'Exception during initial Afterpay Token saving.';

                $this->helper()->log($this->__($message . ' %s', $e->getMessage()), Zend_Log::ERR);

                Mage::throwException(
                        Mage::helper('afterpay')->__($message)
                    );
            }

            return $orderToken;
        }
    
    }

    /**
     * Start creating order for Afterpay
     *
     * @param string                    $orderToken
     * @param string                    $merchantOrderId
     * @param Mage_Sales_Model_Quote    $quote
     *
     * @return mixed
     * @throws Afterpay_Afterpay_Exception
     */
    public function directCapture( $orderToken, $merchantOrderId, $quote ) {

        $postData = $this->getApiAdapter()->buildDirectCaptureRequest($orderToken,$merchantOrderId);

        $gatewayUrl = $this->getApiAdapter()->getApiRouter()->getDirectCaptureApiUrl();

        // Request order token to API
        $result = $this->_sendRequest($gatewayUrl, $postData, Zend_Http_Client::POST, 'StartAfterpayDirectCapture');
        $resultObject = json_decode($result->getBody());

        // Check if token is NOT in response
        if( !empty($resultObject->errorCode) || !empty($resultObject->errorId) ) {

            throw Mage::exception('Afterpay_Afterpay', $resultObject->message);
        }
        else if ( empty($resultObject->id) && empty($resultObject->id) ) {
            throw Mage::exception('Afterpay_Afterpay', 'Afterpay API Gateway Error');
        } 
        else {
            return $resultObject;
        }
    }

    /**
     * Check Afterpay order details using the token
     *
     * @param string                    $orderToken
     * @param Mage_Sales_Model_Quote    $quote
     *
     * @return mixed
     * @throws Afterpay_Afterpay_Exception
     */
    public function getOrderByToken( $orderToken ) {
        $gatewayUrl = $this->getApiAdapter()->getApiRouter()->getOrdersApiUrl( $orderToken, 'token' );

        // Request order token to API
        $result = $this->_sendRequest($gatewayUrl, false, Zend_Http_Client::GET, 'Get order by token ' . $orderToken);
        $resultObject = json_decode($result->getBody());

        return $resultObject;
    }

    /**
     * Placing order to Magento
     *
     * @param $quote
     * @return bool
     * @throws Exception
     */
    public function place(Mage_Sales_Model_Quote $quote)
    {

        // Converting quote to order
        $service = Mage::getModel('sales/service_quote', $quote);

        $service->submitAll();
        $order = $service->getOrder();
	
        //ensure that Grand Total is not doubled
        $order->setBaseGrandTotal( $quote->getBaseGrandTotal() );
        $order->setGrandTotal( $quote->getGrandTotal() );


        //adjust the Quote currency to prevent the default currency being stuck
        $order->setBaseCurrencyCode(Mage::app()->getStore()->getCurrentCurrencyCode());
        $order->setQuoteCurrencyCode(Mage::app()->getStore()->getCurrentCurrencyCode());
        $order->setOrderCurrencyCode(Mage::app()->getStore()->getCurrentCurrencyCode());
        $order->save();


        $session = $this->_getSession();

        if ($order->getId()) {
            // Check with recurring payment
            $profiles = $service->getRecurringPaymentProfiles();
            if ($profiles) {
                $ids = array();
                foreach($profiles as $profile) {
                    $ids[] = $profile->getId();
                }
                $session->setLastRecurringProfileIds($ids);
            }

            //ensure the order amount due is 0
            $order->setTotalDue(0);

            $payment        = $order->getPayment();
            $paymentMethod  = $payment->getMethodInstance();


            // save an order
            $order->setData('afterpay_order_id', $quote->getData('afterpay_order_id'));
            $order->save();

                        
            if (!$order->getEmailSent() && $paymentMethod->getConfigData('order_email')) {
                $order->sendNewOrderEmail();
            }


            // prepare session to success or cancellation page clear current session
            $session->clearHelperData();

            // "last successful quote" for correctly redirect to success page
            $quoteId = $session->getQuote()->getId();
            $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            // an order may be created
            $session->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId());

            //clear the checkout session
            $session->getQuote()->setIsActive(0)->save();

            return true;
        }

        return false;
    }
}