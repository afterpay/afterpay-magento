<?php

/**
 * Abstract base class for Afterpay payment method models
 *
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
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
    protected $_isInitializeNeeded         = false;
    protected $_canFetchTransactionInfo    = false;
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
     * Check method for processing with base currency
     *
     * @param string $currencyCode
     * @return boolean
     */
    public function canUseForCurrency($currencyCode)
    {
        // TODO: Move list of supported currencies to config.xml

        if( strtoupper($currencyCode) === "AUD" || strtoupper($currencyCode) === "NZD" || strtoupper($currencyCode) === "USD" ) {
            return true;
        }

        return false;
    }

    /**
     * @return Afterpay_Afterpay_Helper_Data
     */
    protected function helper()
    {
        return Mage::helper('afterpay');
    }

    /**
     * @return Afterpay_Afterpay_Model_Api_Adapter
     */
    public function getApiAdapter()
    {
        return Mage::getModel('afterpay/api_adapters_adapterv1');
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

    public function refund(Varien_Object $payment, $amount)
    {
        $url = $this->getApiAdapter()->getApiRouter()->getRefundUrl($payment->getData('afterpay_order_id'));
        $helper = $this->helper();
        $coreHelper = Mage::helper('core');

        $helper->log('Refunding order url: ' . $url . ' amount: ' . $amount, Zend_Log::DEBUG);

	if( $amount == 0 ) {
		$helper->log("Zero amount refund is detected, skipping Afterpay API Refunding");
		return $this;
	}

        //Ver 1 needs Merchant Reference variable
        $body = $this->getApiAdapter()->buildRefundRequest($amount, $payment);
        
        $response = $this->_sendRequest($url, $body, $method = Zend_Http_Client::POST);

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

    protected function _sendRequest($url, $body = false, $method = Zend_Http_Client::GET, $call = null)
    {
        $client = new Zend_Http_Client($url);
        $helper = Mage::helper('afterpay');
        $coreHelper = Mage::helper('core');

        $client->setAuth(
            trim($this->_cleanup_string($this->getConfigData(self::API_USERNAME_CONFIG_FIELD))),
            trim($this->_cleanup_string($this->getConfigData(self::API_PASSWORD_CONFIG_FIELD)))
        );

        $client->setConfig(array(
            'useragent' => $this->_construct_user_agent(),
            'timeout'   => 80
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
    public function getPaymentAmounts($method, $tla, $overrides = array() )
    {
        $helper = Mage::helper('afterpay');
        $client = new Zend_Http_Client($this->getApiAdapter()->getApiRouter()->getPaymentUrl($method));

        if( !empty($overrides) && !empty($overrides['website_id']) ) {

            $default_store_id = Mage::getModel('core/website')->load($overrides['website_id'])->getDefaultStore()->getId();

            $merchant_id = trim($this->_cleanup_string(Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_USERNAME_CONFIG_FIELD, $default_store_id)));
            $merchant_key = trim($this->_cleanup_string(Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_PASSWORD_CONFIG_FIELD, $default_store_id)));
           
            $client->setAuth( $merchant_id, $merchant_key);
        }
        else {

            $merchant_id = trim($this->_cleanup_string(Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_USERNAME_CONFIG_FIELD)));
            $merchant_key = trim($this->_cleanup_string(Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_PASSWORD_CONFIG_FIELD)));
            
            $client->setAuth( $merchant_id, $merchant_key);
        }


        //log the credentials used in the Payment Limits Updates
        $helper->log( 'Merchant Origin: ' . $_SERVER['REQUEST_URI'] );
        $helper->log( 'Target URL: ' . $this->getApiAdapter()->getApiRouter()->getPaymentUrl($method) );
        $helper->log( 'Merchant ID: ' . $merchant_id );
        $masked_merchant_key = substr($merchant_key, 0, 4) . '****' . substr($merchant_key, -4);
        $helper->log( 'Merchant Key: ' . $masked_merchant_key );

        $client->setConfig(array(
            'useragent' => $this->_construct_user_agent(),
            'timeout'   => 80
        ));

        $response = $client->request();

        if ($response->isError()) {
            $helper->log($response);
            throw Mage::exception('Afterpay_Afterpay', 'Afterpay API error: ' . 'Payment Limits Update Error. Please check Merchant ID and Key.');
        }

        $data = Mage::helper('core')->jsonDecode($response->getBody());

        if( empty($data) || count($data) < 1 ) {
            throw Mage::exception('Afterpay_Afterpay', 'Afterpay API error: ' . 'Empty Payment Limits Update Results. Please check Merchant ID and Key.');
        }

        foreach ($data as $info) {
            if ($info['type'] == $tla) {
                return $info;
            }
        }

        return false;
    }



    /**
     * Filters the String for secret keys
     *
     * @return string Authorization code 
     * @since 1.0.0
     */
    private function _cleanup_string($string) {
        $result = preg_replace("/[^a-zA-Z0-9]+/", "", $string);
        return $result;
    }

    private function _construct_user_agent() {
        return 'AfterpayMagentoPlugin/' . $this->helper()->getModuleVersion() . 
                ' (Magento ' . Mage::getEdition() . ' ' . Mage::getVersion() . 
                ') MerchantID: ' . trim($this->_cleanup_string($this->getConfigData(self::API_USERNAME_CONFIG_FIELD))) . 
                ' URL: ' . Mage::getBaseUrl();
    }
}
