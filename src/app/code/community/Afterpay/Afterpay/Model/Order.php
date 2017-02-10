<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
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
        /**
         * In some checkout extension the post data used rather than cart session
         *
         * Adding post data to put in cart session
         */
        $params = Mage::app()->getRequest()->getParams();
        if ($params) {
            $this->_saveCart($params);
        }

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
        $result = $this->_sendRequest($gatewayUrl, $postData, Varien_Http_Client::POST, 'StartAfterpayPayment');
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

            $payment = $quote->getPayment();
            $payment->setData('afterpay_token', $orderToken);
            $payment->save();

            // Added to log
            Mage::helper('afterpay')->log(
                sprintf('Token successfully saved for reserved order %s. token=%s', $quote->getReservedOrderId(), $orderToken),
                Zend_Log::NOTICE
            );

            return $orderToken;
        }
    
    }

    /**
     * Start creating order for Afterpay
     *
     * @param string                    $token
     * @param string                    $merchantOrderId
     * @param Mage_Sales_Model_Quote    $quote
     *
     * @return mixed
     * @throws Afterpay_Afterpay_Exception
     */
    public function directCapture( $orderToken, $merchantOrderId, $quote ) {

        //need to do a check for stock levels here
        if( empty($orderToken) ) {
            // Perform the fallback in case of Unsupported checkout
            $this->fallbackMechanism('token_missing');
        }

        $postData = $this->getApiAdapter()->buildDirectCaptureRequest($orderToken,$merchantOrderId);

        $gatewayUrl = $this->getApiAdapter()->getApiRouter()->getDirectCaptureApiUrl();

        // Request order token to API
        $result = $this->_sendRequest($gatewayUrl, $postData, Varien_Http_Client::POST, 'StartAfterpayDirectCapture');
        $resultObject = json_decode($result->getBody());

        // Check if token is NOT in response
        if( !empty($resultObject->errorCode) || !empty($resultObject->errorId) ) {

            // Perform the fallback in case of Unsupported checkut
            $this->fallbackMechanism($resultObject->errorCode);

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
     * Fallback Mechanism hwen Capture is failing
     *
     * @param string    $error_code
     *
     * @return void
     * @throws Afterpay_Afterpay_Exception
     */
    private function fallbackMechanism($error_code) {

        // Perform the fallback in case of Unsupported checkut
        try {

            //Unsupported checkout with unattached payovertime.js
            //Or checkout with payovertime.js attached, but no checkout specific JS codes
            $error_array = array(
                'invalid_object'
                , 'invalid_order_transaction_status'
                , 'token_missing'
                , 'invalid_token'
            );

            if( in_array($error_code, $error_array) ) {

                Mage::helper('afterpay')->log(
                    sprintf('Unsupported Checkout detected, starting fallback mechanism: ' . $error_code ),
                    Zend_Log::NOTICE
                );

                $fallback_url = "/afterpay/payment/redirectFallback";
                        
                Mage::app()->getResponse()->setRedirect($fallback_url);
                Mage::app()->getResponse()->sendResponse();
                exit;
            }
        }
        catch( Exception $e ) {
            throw Mage::exception('Afterpay_Afterpay', $e->getMessage());
        }
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
        $quote->save();
        $order = $service->getOrder();
	
	//ensure that Grand Total is not doubled
        $order->setBaseGrandTotal( $quote->getBaseGrandTotal() );
        $order->setGrandTotal( $quote->getGrandTotal() );


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

            //set the Afterpay Order ID to be sure
            // $payment->setData('afterpay_order_id', $quote->getAfterpayOrderId());
            // $payment->setAfterpayOrderId($quote->getAfterpayOrderId());
	        // $payment->save();

            // save an order
            $order->setAfterpayOrderId($quote->getAfterpayOrderId());
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

            return true;
        }

        return false;
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
                Mage::throwException(Mage::helper('afterpay')->__('%s', $result['message']));
            }
        }
    }
}