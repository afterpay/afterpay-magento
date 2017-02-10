<?php

/**
 * Abstract base class for Afterpay payment method models
 *
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */

/**
 * Class Afterpay_Afterpay_Model_Method_Base
 */
abstract class Afterpay_Afterpay_Model_Method_Base extends Mage_Payment_Model_Method_Abstract
{
    /* Configuration fields */
    const API_ENABLED_FIELD = 'active';

    const API_MODE_CONFIG_FIELD = 'api_mode';

    const API_MIN_ORDER_TOTAL_FIELD = 'min_order_total';
    const API_MAX_ORDER_TOTAL_FIELD = 'max_order_total';

    const API_URL_CONFIG_PATH_PATTERN = 'afterpay/api/{prefix}_api_url';
    const WEB_URL_CONFIG_PATH_PATTERN = 'afterpay/api/{prefix}_web_url';

    const API_USERNAME_CONFIG_FIELD = 'api_username';
    const API_PASSWORD_CONFIG_FIELD = 'api_password';

    /* Order payment statuses */
    const RESPONSE_STATUS_APPROVED = 'APPROVED';
    const RESPONSE_STATUS_PENDING  = 'PENDING';
    const RESPONSE_STATUS_FAILED   = 'FAILED';
    const RESPONSE_STATUS_DECLINED = 'DECLINED';

    const TRUNCATE_SKU_LENGTH = 128;

    /**
     * Payment Method features common for all payment methods
     *
     * @var bool
     */
    protected $_isGateway                  = false;
    protected $_canOrder                   = true;
    protected $_canAuthorize               = false;
    protected $_canCapture                 = false;
    protected $_canCapturePartial          = false;
    protected $_canCaptureOnce             = false;
    protected $_canRefund                  = true;
    protected $_canRefundInvoicePartial    = true;
    protected $_canVoid                    = false;
    protected $_canUseInternal             = false;
    protected $_canUseCheckout             = true;
    protected $_canUseForMultishipping     = false;
    protected $_isInitializeNeeded         = true;
    protected $_canFetchTransactionInfo    = true;
    protected $_canReviewPayment           = true;
    protected $_canCreateBillingAgreement  = false;
    protected $_canManageRecurringProfiles = false;

    /**
     * Payment type code according to Afterpay API documentation.
     *
     * @var string
     */
    protected $afterPayPaymentTypeCode = null;

    /**
     * Get config payment action
     *
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return $this->getConfigData('payment_action');
    }

    /**
     * Check method for processing with base currency
     *
     * @param string $currencyCode
     * @return boolean
     */
    public function canUseForCurrency($currencyCode)
    {
        // TODO: Move list of supported currencies to config.xml
        return strtoupper($currencyCode) === "AUD";
    }

    /**
     * @return Afterpay_Afterpay_Helper_Data
     */
    protected function helper()
    {
        return Mage::helper('afterpay');
    }

    /**
     * flag for API version
     *
     * @return bool
     */
    public function isAPIVersion1()
    {
        if( $this->getConfigPaymentAction() == Afterpay_Afterpay_Model_Method_Payovertime::ACTION_AUTHORIZE_CAPTURE ) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @return Afterpay_Afterpay_Model_Api_Adapter
     */
    public function getApiAdapter()
    {   
        if ( $this->isAPIVersion1() ) {
            return Mage::getModel('afterpay/api_adapters_adapterv1');
        }
        else {
            return Mage::getModel('afterpay/api_adapters_adapter');
        }
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string        $paymentAction
     * @param Varien_Object $stateObject
     *
     * @return Afterpay_Afterpay_Model_Method_Base
     *
     * @throws Mage_Payment_Model_Info_Exception
     */
    public function initialize($paymentAction, $stateObject)
    {
        try {

            /** @var Mage_Sales_Model_Order_Payment $payment */
            $payment = $this->getInfoInstance();
            $this->retrieveOrderToken($payment);

            $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
            $stateObject->setStatus('pending_payment');
            $stateObject->setIsNotified(false);

        } catch (Afterpay_Afterpay_Exception $e) {
            Mage::logException($e);
            $this->helper()->log('Payment method initialization error: ' . $e->getMessage(), Zend_Log::ERR);

            throw new Mage_Payment_Model_Info_Exception(
                Mage::helper('afterpay')->__('Afterpay Error: Order cannot be processed')
            );
        } catch (LogicException $e) {
            Mage::logException($e);
            $this->helper()->log('Payment method initialization error: ' . $e->getMessage(), Zend_Log::ERR);

            throw $e;
        }

        return $this;
    }

    /**
     * Order payment
     *
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @throws Exception
     * @throws Mage_Core_Exception
     * @throws Afterpay_Afterpay_Exception
     *
     * @return Afterpay_Afterpay_Model_Method_Base
     */
    public function order(Varien_Object $payment, $amount)
    {
        parent::order($payment, $amount);

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $order         = $payment->getOrder();
        $transactionId = $payment->getData('afterpay_order_id');

        if ($order->getState() !== Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
            throw Mage::exception('Afterpay_Afterpay',
                sprintf('Afterpay: Trial to order payment for non-pending order: order_id=%s state=%s status=%s afterpay_order_id=%s',
                    $order->getIncrementId(), $order->getState(), $order->getStatus(), $transactionId));
        }

        $payment->setTransactionId($transactionId);

        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER);
        $transaction->setIsClosed(false);
        $transaction->save();

        $payment->setLastTransId($transactionId);

        $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
        $order->setStatus($this->getConfigData('payment_review_status'));

        return $this;
    }

    /**
     * Fetch transaction info
     *
     * @param Mage_Payment_Model_Info $payment
     * @param string                  $transactionId
     * @return array
     *
     * @throws Mage_Core_Exception
     * @throws Afterpay_Afterpay_Exception
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        $paymentInfo = $transactionId ?
            $this->retrievePaymentInfoByTxnId($transactionId) :
            $this->retrievePaymentInfoByToken($payment->getData('afterpay_token'));

        // update transaction info
        $txn = $this->loadTransaction($payment->getId(), $transactionId);
        if ($txn) {
            $txn->setOrderPaymentObject($payment);
            $txn->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $paymentInfo);
            $txn->save();
        }

        switch ($paymentInfo['status']) {

            case self::RESPONSE_STATUS_PENDING:
                // no action
                break;

            case self::RESPONSE_STATUS_APPROVED:
                if ($txn) {
                    $txn->close(true);
                }

                $payment->setTransactionId($transactionId);
                $payment->setIsTransactionApproved(true);
                break;

            case self::RESPONSE_STATUS_FAILED:
            case self::RESPONSE_STATUS_DECLINED:
                if ($txn) {
                    $txn->close(true);
                }

                $payment->setTransactionId($transactionId);
                $payment->setIsTransactionDenied(true);
                break;

            default:
                throw Mage::exception('Afterpay_Afterpay', 'Afterpay: Unknown payment status: ' . $paymentInfo['status']);
        }

        return $paymentInfo;
    }

    /**
     * @param string $paymentId
     * @param string $transactionId
     * @return Mage_Sales_Model_Order_Payment_Transaction|null
     */
    protected function loadTransaction($paymentId, $transactionId)
    {
        /** @var Mage_Sales_Model_Resource_Order_Payment_Transaction_Collection $txnCollection */
        $txnCollection = Mage::getResourceModel('sales/order_payment_transaction_collection');
        $txnCollection->addPaymentIdFilter($paymentId)
            ->addFieldToFilter('txn_id', $transactionId);

        $txn = $txnCollection->getFirstItem();

        return ($txn->getId()) ? $txn : null;
    }

    /**
     * Attempt to accept a payment that us under review
     *
     * @param Mage_Payment_Model_Info $payment
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function acceptPayment(Mage_Payment_Model_Info $payment)
    {
        parent::acceptPayment($payment);

        return true;
    }

    /**
     * Attempt to deny a payment that us under review
     *
     * @param Mage_Payment_Model_Info $payment
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function denyPayment(Mage_Payment_Model_Info $payment)
    {
        parent::denyPayment($payment);

        return true;
    }

    /**
     * Retrieve Afterpay token via API call
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @throws Mage_Core_Exception
     * @throws Afterpay_Afterpay_Exception
     */
    protected function retrieveOrderToken(Mage_Sales_Model_Order_Payment $payment)
    {
        $order = $payment->getOrder();
        $postData = $this->getApiAdapter()->buildOrderTokenRequest($order, array(), $this->afterPayPaymentTypeCode);
        $gatewayUrl = $this->getApiAdapter()->getApiRouter()->getOrdersApiUrl();

        $helper = $this->helper();
        $helper->log(
            'retrieveOrderToken() request: ' . print_r(
                array(
                    'order_id'    => $order->getIncrementId(),
                    'gateway_url' => $gatewayUrl,
                    'post_data'   => $postData
                ),
                true
            ),
            Zend_Log::DEBUG
        );

        $result = $this->_sendRequest($gatewayUrl, $postData, Varien_Http_Client::POST);

        if ($result->isError()) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: %s', $result->getMessage())
            );
        }

        $resultObject = json_decode($result->getBody(), true);

        $helper->log(
            'retrieveOrderToken() response: ' . print_r(
                array(
                    'order_id'    => $order->getIncrementId(),
                    'gateway_url' => $gatewayUrl,
                    'result'      => $resultObject
                ),
                true
            ),
            Zend_Log::DEBUG
        );

        if (isset($resultObject['errorId']) || isset($resultObject['errorCode'])) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: %s', $resultObject['message'])
            );
        }

        $orderToken = $resultObject['orderToken'];

        if (empty($orderToken)) {

            throw Mage::exception(
                'Afterpay_Afterpay',
                'Afterpay API Error: Cannot get token for order ' . $order->getIncrementId()
            );

        } else {
            $payment->setData('afterpay_token', $orderToken);

            $helper->log(
                sprintf('New order token for order %s: token=%s', $order->getIncrementId(), $orderToken),
                Zend_Log::INFO
            );
        }

        return $orderToken;
    }

    /**
     * Retrieve payment status via API call
     *
     * @param string $afterPayOrderId
     * @param  string $callName
     *
     * @returns array
     *
     * @throws Mage_Core_Exception
     * @throws Afterpay_Afterpay_Exception
     */
    protected function retrievePaymentInfoByTxnId($afterPayOrderId, $callName = 'retrievePaymentInfo')
    {
        $url =  $this->getApiAdapter()->getApiRouter()->getOrdersApiUrl($afterPayOrderId, 'id');

        $helper = $this->helper();

        $result = $this->_sendRequest($url, false, Varien_Http_Client::GET, $callName);

        if ($result->isError()) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: %s', $result->getMessage())
            );
        }

        $resultObject = json_decode($result->getBody(), true);

        if (isset($resultObject['errorId']) || isset($resultObject['errorCode'])) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: %s', $resultObject['message'])
            );
        }

        return array(
            'id'                => $resultObject['id'],
            'token'             => $resultObject['token'],
            'status'            => $resultObject['status'],
            'orderDate'         => !empty($resultObject['orderDate']) ? $resultObject['orderDate'] : '',
            'paymentType'       => !empty($resultObject['paymentType']) ? $resultObject['paymentType'] : 'PBI',
            'consumerEmail'     => !empty($resultObject['consumer']['email']) ? $resultObject['consumer']['email'] : '',
            'consumerName'      => !empty($resultObject['consumer']['givenNames']) && !empty($resultObject['consumer']['surname']) ? $resultObject['consumer']['givenNames'] . ' ' . $resultObject['consumer']['surname'] : '',
            'consumerTelephone' => !empty($resultObject['consumer']['mobile']) ? $resultObject['consumer']['mobile'] : '',
            'merchantOrderId'   => !empty($resultObject['orderDetail']['merchantOrderId']) ? $resultObject['orderDetail']['merchantOrderId'] : '',
            'amount'            => !empty($resultObject['orderDetail']['orderAmount']['amount']) ? $resultObject['orderDetail']['orderAmount']['amount'] : '',
        );
    }

    /**
     * Search order information by Afterpay Token
     *
     * @param string $afterpayToken
     * @returns array Returns null if no order info has been found
     *
     * @throws Mage_Core_Exception
     * @throws Afterpay_Afterpay_Exception
     */
    public function retrievePaymentInfoByToken($afterpayToken)
    {
        //API Version 1 should not even use this function
        //but just in case I will implement the fallback for token searching
        $url = $this->getApiAdapter()->getApiRouter()->getOrdersApiUrl($afterpayToken, 'token');

        $helper = $this->helper();
        $helper->log('Searching for order information, request url: ' . $url, Zend_Log::DEBUG);

        $result = $this->_sendRequest($url);

        if ($result->isError()) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: %s', $result->getMessage())
            );
        }

        $resultObject = json_decode($result->getBody(), true);

        if (isset($resultObject['errorId']) || isset($resultObject['errorCode'])) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: %s', $resultObject['message'])
            );
        }

        $helper->log("retrievePaymentInfoByToken() Lookup results:\n" . print_r($resultObject, true), Zend_Log::DEBUG);

        if ($resultObject['totalResults'] == 0) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: Order info lookup didn\'t return any orders: token=%s', $afterpayToken)
            );
        } else if ($resultObject['totalResults'] > 1) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: Order info lookup found %s orders by token=%s (supposed to get only one)',
                    $resultObject['totalResults'], $afterpayToken
                )
            );
        }

        $orderData = $resultObject['results'][0];
        $helper->log(
            sprintf(
                'Payment info was successfully fetched for token=%s: order_id=%s, afterpay_status=%s',
                $afterpayToken,
                $orderData['id'],
                $orderData['status']
            ),
            Zend_Log::INFO
        );

        return array(
            'id'                => $orderData['id'],
            'token'             => $orderData['token'],
            'status'            => $orderData['status'],
            'orderDate'         => $orderData['orderDate'],
            'paymentType'       => $orderData['paymentType'],
            'consumerEmail'     => $orderData['consumer']['email'],
            'consumerName'      => $orderData['consumer']['givenNames'] . ' ' . $orderData['consumer']['surname'],
            'consumerTelephone' => $orderData['consumer']['mobile'],
            'merchantOrderId'   => $resultObject['orderDetail']['merchantOrderId'],
            'amount'            => $resultObject['orderDetail']['orderAmount']['amount'],
        );
    }

    /**
     * Get authorization HTTP header value
     *
     * @return string
     */
    protected function getAuthorizationHeader()
    {
        $merchantId     = trim($this->_cleanup_string($this->getConfigData(self::API_USERNAME_CONFIG_FIELD)));
        $merchantSecret = trim($this->_cleanup_string($this->getConfigData(self::API_PASSWORD_CONFIG_FIELD)));

        return 'Authorization: Basic ' . base64_encode($merchantId . ':' . $merchantSecret);
    }

    /**
     * @param int    $afterPayOrderId
     * @param string $trackingNumber
     * @param string $courier
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function sendShippedApiRequest($afterPayOrderId, $trackingNumber, $courier)
    {
        $url = $this->getApiAdapter()->getApiRouter()->getOrdersApiUrl( $afterPayOrderId, "courier" );
        $postData   = $this->getApiAdapter()->buildSetShippedRequest($trackingNumber, $courier);

        // $url = (substr($gatewayUrl, -1) == '/' ? $gatewayUrl : $gatewayUrl . '/') . $afterPayOrderId . '/shippedstatus';

        $helper = $this->helper();
        $helper->log('Sending SetShipped Api request to url: ' . $url, Zend_Log::DEBUG);
        $helper->log('sendShippedApiRequest() request: ' . print_r($postData, true), Zend_Log::DEBUG);

        $response       = $this->_sendRequest($url, $postData, Varien_Http_Client::PUT);
        $httpStatusCode = $response->getStatus();
        $contents       = $response->getBody();

        // log info about HTTP status code
        $helper->log(
            sprintf(
                'Shipped API request has been sent for afterpay_order_id=%s: HTTP status: %s',
                $afterPayOrderId,
                $httpStatusCode
            ),
            Zend_Log::INFO
        );

        // this is special http status code for not approved orders
        if ($httpStatusCode == 412) {
            $message = sprintf(
                "Order can't be located or it is not in an approved state; afterpay_order_id=%s: HTTP status: %s; HTTP result content:\n%s",
                $afterPayOrderId,
                $httpStatusCode,
                $contents
            );
            $helper->log($message, Zend_Log::WARN);
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: %s', $message)
            );
        }

        // all HTTP error codes
        if ($httpStatusCode >= 400) {
            $message = Mage::helper('afterpay')->__(
                'Afterpay API unknown Error; http status: %s; result: %s',
                $httpStatusCode,
                $contents
            );
            $helper->log($message, Zend_Log::ERR);
            throw Mage::exception('Afterpay_Afterpay', $message);
        }

        return $this;
    }

    /**
     * Parse 1st line in HTTP response and return HTTP status code
     *
     * @param array $metadata HTTP stream metadata returned by stream_get_meta_data()
     * @return int Returns -1 in case of error
     */
    protected function parseHttpReplyCode($metadata)
    {
        if (!is_array($metadata) || !isset($metadata['wrapper_data'])
            || !isset($metadata['wrapper_type']) || ($metadata['wrapper_type'] != 'http')
        ) {
            return -1;
        }

        $matches = null;
        preg_match('/^HTTP\/[\d.]+\s+(\d+).*/', $metadata['wrapper_data'][0], $matches);

        if (isset($matches[1])) {
            return intval($matches[1]);
        } else {
            return -1;
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string                 $afterpayOrderId
     *
     * @return $this
     */
    public function saveAfterpayOrderId(Mage_Sales_Model_Order $order, $afterpayOrderId)
    {
        $payment = $order->getPayment();

        if ($afterpayOrderId !== $payment->getData('afterpay_order_id')) {
            // save given order id - it will be used for API calls
            $this->helper()->log(
                sprintf(
                    'Associating Magento order %s with following Afterpay Order: %s',
                    $order->getIncrementId(),
                    $afterpayOrderId
                ),
                Zend_Log::INFO
            );

            $payment->setData('afterpay_order_id', $afterpayOrderId);
            $payment->save();
        }

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param bool                   $inReview
     *
     * @return $this
     *
     * @throws Afterpay_Afterpay_Exception
     */
    public function createOrderTransaction(Mage_Sales_Model_Order $order, $inReview)
    {
        // create order transaction
        $amount = Mage::app()->getStore($order->getStoreId())->roundPrice($order->getBaseTotalDue());

        $helper  = $this->helper();
        $payment = $order->getPayment();

        if ($order->getState() !== Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
            throw new Afterpay_Afterpay_Exception(
                sprintf(
                    'Order transaction isn\'t created. It is allowed for Pending Payment orders only: order_state=%s order_status=%s order_increment_id=%s payment_id=%s amount=%s',
                    $order->getState(),
                    $order->getStatus(),
                    $order->getIncrementId(),
                    $payment->getId(),
                    $amount
                )
            );
        }

        $helper->log(
            sprintf(
                'Creating order transaction: order_increment_id=%s payment_id=%s amount=%s',
                $order->getIncrementId(),
                $payment->getId(),
                $amount
            ),
            Zend_Log::INFO
        );

        $this->order($payment, $amount);

        if ($inReview) {
            $message = $helper->__(
                'Ordering amount of %s is pending approval on gateway.',
                $order->getBaseCurrency()->formatTxt($amount)
            );
            $message .= ' ' . $helper->__('Transaction ID: "%s".', $payment->getLastTransId());

            $order->addStatusHistoryComment($message);
        }

        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        $url = $this->getApiAdapter()->getApiRouter()->getRefundUrl($payment->getAfterpayOrderId());
        $helper = $this->helper();
        $coreHelper = Mage::helper('core');

        $helper->log('Refunding order url: ' . $url . ' amount: ' . $amount, Zend_Log::DEBUG);

        //Ver 1 needs Merchant Reference variable
        $body = $this->getApiAdapter()->buildRefundRequest($amount, $payment);
        
        $response = $this->_sendRequest($url, $body, $method = Varien_Http_Client::POST);

        if ($response->isError()) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: %s', $response->getMessage())
            );
        }

        $resultObject = $coreHelper->jsonDecode($response->getBody(), true);

        if (isset($resultObject['errorId']) || isset($resultObject['errorCode'])) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                Mage::helper('afterpay')->__('Afterpay API Error: %s', $resultObject['message'])
            );
        }

        $helper->log("refund results:\n" . print_r($resultObject, true), Zend_Log::DEBUG);

        return $this;
    }

    protected function _sendRequest($url, $body = false, $method = Varien_Http_Client::GET, $call = null)
    {
        $client = new Varien_Http_Client($url);
        $helper = Mage::helper('afterpay');
        $coreHelper = Mage::helper('core');

        $client->setAuth(
            trim($this->_cleanup_string($this->getConfigData(self::API_USERNAME_CONFIG_FIELD))),
            trim($this->_cleanup_string($this->getConfigData(self::API_PASSWORD_CONFIG_FIELD)))
        );

        $client->setConfig(array(
            'useragent' => 'AfterpayMagentoPlugin/' . $helper->getModuleVersion() . ' (Magento ' . Mage::getEdition() . ' ' . Mage::getVersion() . ')',
            'timeout' => 80
        ));

        if ($body !== false) {
            $client->setRawData($coreHelper->jsonEncode($body), 'application/json');
        }

        // Do advanced logging before
        $helper->log(array(
            'url' => $url,
            'type' => 'request',
            'call' => $call,
            'body' => $body
        ), Zend_Log::DEBUG);

        $response = $client->request($method);

        // Do advanced logging after
        $helper->log(array(
            'url' => $url,
            'type' => 'response',
            'call' => $call,
            'body' => ($response->getBody() && json_decode($response->getBody())) ? json_decode($response->getBody()) : $response,
        ), Zend_Log::DEBUG);

        return $response;
    }

    /**
     * Get the valid order limits for a specific payment method
     *
     * @param string $method    method to get payment methods for
     * @param string $tla       Three Letter Acronym used by Afterpay for the method
     * @return array|bool
     * @throws Mage_Core_Exception
     * @throws Zend_Http_Client_Exception
     */
    public function getPaymentAmounts($method, $tla)
    {
        $helper = Mage::helper('afterpay');
        $client = new Varien_Http_Client($this->getApiAdapter()->getApiRouter()->getPaymentUrl($method));
        $client->setAuth(
            trim($this->_cleanup_string(Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_USERNAME_CONFIG_FIELD))),
            trim($this->_cleanup_string(Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_PASSWORD_CONFIG_FIELD)))
        );

        $client->setConfig(array(
            'useragent' => 'AfterpayMagentoPlugin/' . $helper->getModuleVersion() . ' (Magento ' . Mage::getEdition() . ' ' . Mage::getVersion() . ')'
        ));

        $response = $client->request();

        if ($response->isError()) {
            throw Mage::exception('Afterpay_Afterpay', 'Afterpay API error: ' . $response->getMessage());
        }
        $data = Mage::helper('core')->jsonDecode($response->getBody());

        foreach ($data as $info) {
            if ($info['type'] == $tla) {
                return $info;
            }
        }

        return false;
    }



    /**
     * Filters the String for screcret keys
     *
     * @return string Authorization code 
     * @since 1.0.0
     */
    private function _cleanup_string($string) {
        $result = preg_replace("/[^a-zA-Z0-9]+/", "", $string);
        return $result;
    }
}
