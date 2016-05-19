<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */

/**
 * Class Afterpay_Afterpay_Model_Api_Adapter
 *
 * Building API requests and parsing API responses.
 */
class Afterpay_Afterpay_Model_Api_Adapter
{
    public function buildOrderTokenRequest(Mage_Sales_Model_Order $order, $afterPayPaymentTypeCode)
    {
        // TODO: Add warning log in case if rounding changes amount, because it's potential problem
        $precision = 2;

        if (empty($afterPayPaymentTypeCode)) {
            throw new InvalidArgumentException('Payment type code cannot be empty');
        }

        $params = array(
            'paymentType' => $afterPayPaymentTypeCode
        );

        $orderData = $order->getData();

        $billingAddress  = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $params['consumer'] = array(
            'email'      => (string)$order->getCustomerEmail(),
            'givenNames' => (string)$order->getCustomerFirstname(),
            'surname'    => (string)$order->getCustomerLastname(),
            'mobile'     => (string)$billingAddress->getTelephone()
        );

        $params['orderDetail'] = array(
            'merchantOrderDate' => strtotime($order->getCreatedAt()) * 1000,
            'merchantOrderId'   => $order->getIncrementId(),
            'shippingPriority'  => 'STANDARD',
            'items'             => array()
        );

        foreach ($order->getAllVisibleItems() as $orderItem) {
            /** @var Mage_Sales_Model_Order_Item $orderItem */
            $params['orderDetail']['items'][] = array(
                'name'     => (string)$orderItem->getName(),
                'sku'      => (string)$orderItem->getSku(),
                'quantity' => (int)$orderItem->getQtyOrdered(),
                'price'    => array(
                    'amount'   => round((float)$orderItem->getPriceInclTax(), $precision),
                    'currency' => (string)$orderData['order_currency_code']
                )
            );
        }

        if ($order->getShippingInclTax()) {
            $params['orderDetail']['shippingCost'] = array(
                'amount'   => round((float)$order->getShippingInclTax(), $precision), // with tax
                'currency' => (string)$orderData['order_currency_code']
            );
        }

        if ($orderData['discount_amount']) {
            $params['orderDetail']['discountType'] = 'Discount';
            $params['orderDetail']['discount']     = array(
                'amount'   => round((float)$orderData['discount_amount'], $precision),
                'currency' => (string)$orderData['order_currency_code']
            );
        }

        $params['orderDetail']['includedTaxes'] = array(
            'amount'   => round((float)$orderData['tax_amount'], $precision),
            'currency' => (string)$orderData['order_currency_code']
        );

        $params['orderDetail']['subTotal'] = array(
            'amount'   => round((float)$orderData['subtotal'], $precision),
            'currency' => (string)$orderData['order_currency_code'],
        );

        $params['orderDetail']['shippingAddress'] = array(
            'name'     => (string)$shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname(),
            'address1' => (string)$shippingAddress->getStreet1(),
            'address2' => (string)$shippingAddress->getStreet2(),
            'suburb'   => (string)$shippingAddress->getCity(),
            'postcode' => (string)$shippingAddress->getPostcode(),
        );

        $params['orderDetail']['billingAddress'] = array(
            'name'     => (string)$billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
            'address1' => (string)$billingAddress->getStreet1(),
            'address2' => (string)$billingAddress->getStreet2(),
            'suburb'   => (string)$billingAddress->getCity(),
            'postcode' => (string)$billingAddress->getPostcode()
        );

        $params['orderDetail']['orderAmount'] = array(
            'amount'   => round((float)$order->getGrandTotal(), $precision),
            'currency' => (string)$order->getOrderCurrencyCode(),
        );

        return $params;
    }

    /**
     * @param string $trackingNumber
     * @param string $courier
     *
     * @return array
     */
    public function buildSetShippedRequest($trackingNumber, $courier)
    {
        return array(
            'trackingNumber' => is_null($trackingNumber) ? null : (string)$trackingNumber,
            'courier'        => is_null($courier) ? null : (string)$courier
        );
    }

    /**
     * Get the URL for valid payment types
     *
     * @param string $method Which payment method to get the URL for
     * @return string
     */
    public function getPaymentUrl($method)
    {
        $apiMode      = Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_MODE_CONFIG_FIELD);
        $settings     = Afterpay_Afterpay_Model_System_Config_Source_ApiMode::getEnvironmentSettings($apiMode);

        return $settings[Afterpay_Afterpay_Model_System_Config_Source_ApiMode::KEY_API_URL] . 'merchants/valid-payment-types';
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
        $client = new Varien_Http_Client($this->getPaymentUrl($method));
        $client->setAuth(
            trim(Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_USERNAME_CONFIG_FIELD)),
            trim(Mage::getStoreConfig('payment/' . $method . '/' . Afterpay_Afterpay_Model_Method_Base::API_PASSWORD_CONFIG_FIELD))
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
}

