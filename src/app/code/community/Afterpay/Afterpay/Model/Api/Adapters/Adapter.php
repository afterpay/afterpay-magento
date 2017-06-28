<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Aferpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au)
 */

/**
 * Class Afterpay_Afterpay_Model_Api_Adapters_Adapter
 *
 * Building API requests and parsing API responses.
 */
class Afterpay_Afterpay_Model_Api_Adapters_Adapter
{

    /**
     * @return Afterpay_Afterpay_Model_Api_Router
     */
    public function getApiRouter()
    {   
        return Mage::getModel('afterpay/api_routers_router');
    }

    public function buildOrderTokenRequest($object, $override = array(), $afterPayPaymentTypeCode = NULL)
    {
        // TODO: Add warning log in case if rounding changes amount, because it's potential problem
        $precision = 2;

        if (empty($afterPayPaymentTypeCode)) {
            throw new InvalidArgumentException('Payment type code cannot be empty');
        }

        $this->_validateData($object);

        $params = array(
            'paymentType' => $afterPayPaymentTypeCode
        );

        $data = $object->getData();

        $billingAddress  = $object->getBillingAddress();

        $shippingAddress = $object->getShippingAddress();
        if( empty($shippingAddress) ) {
            $shippingAddress = $object->getBillingAddress();
        }

        $params['consumer'] = array(
            'email'      => (string)$object->getCustomerEmail(),
            'givenNames' => (string)$object->getCustomerFirstname(),
            'surname'    => (string)$object->getCustomerLastname(),
            'mobile'     => (string)$billingAddress->getTelephone()
        );

        $params['orderDetail'] = array(
            'merchantOrderDate' => strtotime($object->getCreatedAt()) * 1000,
            'merchantOrderId'   => array_key_exists('merchantOrderId', $override) ? $override['merchantOrderId'] : $object->getIncrementId(),
            'shippingPriority'  => 'STANDARD',
            'items'             => array()
        );

        foreach ($object->getAllVisibleItems() as $item) {
            /** @var Mage_Sales_Model_Order_Item $orderItem */
            $params['orderDetail']['items'][] = array(
                'name'     => (string)$item->getName(),
                'sku'      => $this->_truncateString( (string)$item->getSku() ),
                'quantity' => (int)$item->getQty(),
                'price'    => array(
                    'amount'   => round((float)$item->getPriceInclTax(), $precision),
                    'currency' => (string)$data['store_currency_code']
                )
            );
        }

        if ($object->getShippingInclTax()) {
            $params['orderDetail']['shippingCost'] = array(
                'amount'   => round((float)$object->getShippingInclTax(), $precision), // with tax
                'currency' => (string)$data['store_currency_code']
            );
        }

        if (isset($data['discount_amount'])) {
            $params['orderDetail']['discountType'] = 'Discount';
            $params['orderDetail']['discount']     = array(
                'amount'   => round((float)$data['discount_amount'], $precision),
                'currency' => (string)$data['store_currency_code']
            );
        }

        $taxAmount = array_key_exists('tax_amount',$data) ? $data['tax_amount'] : $shippingAddress->getTaxAmount();
        $params['orderDetail']['includedTaxes'] = array(
            'amount'   => isset($taxAmount) ? round((float)$taxAmount, $precision) : 0,
            'currency' => (string)$data['store_currency_code']
        );

        $params['orderDetail']['subTotal'] = array(
            'amount'   => round((float)$data['subtotal'], $precision),
            'currency' => (string)$data['store_currency_code'],
        );

        $params['orderDetail']['shippingAddress'] = array(
            'name'     => (string)$shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname(),
            'address1' => (string)$shippingAddress->getStreet1(),
            'address2' => (string)$shippingAddress->getStreet2(),
            'suburb'   => (string)$shippingAddress->getCity(),
            'postcode' => (string)$shippingAddress->getPostcode(),
            'state'    => (string)$shippingAddress->getRegion(),
        );

        $params['orderDetail']['billingAddress'] = array(
            'name'     => (string)$billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
            'address1' => (string)$billingAddress->getStreet1(),
            'address2' => (string)$billingAddress->getStreet2(),
            'suburb'   => (string)$billingAddress->getCity(),
            'state'    => (string)$billingAddress->getRegion(),
            'postcode' => (string)$billingAddress->getPostcode()
        );

        $params['orderDetail']['orderAmount'] = array(
            'amount'   => round((float)$object->getGrandTotal(), $precision),
            'currency' => (string)$data['store_currency_code'],
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
     * Get the URL for Refunds API Ver 0
     *
     * @param float $amount
     * @param object $payment
     *
     * @param string $method Which payment method to get the URL for
     * @return array
     */
    public function buildRefundRequest($amount, $payment)
    {
        $params['amount'] = array(
                                'amount'    => abs($amount) * -1, // Afterpay API requires a negative amount
                                'currency'  => $payment->getOrder()->getGlobalCurrencyCode(),
                            );

        $params['merchantRefundId'] = NULL;

        return $params;
    }


    /**
     * Since 0.12.7
     * Truncate the string in case of very long custom values
     *
     * @param string $string    string to truncate
     * @param string $length    string truncation length
     * @param string $appendStr    string to be appended after truncate
     * @return string
     */
    private function _truncateString($string, $length = 64, $appendStr = "") {
        $truncated_str = "";
        $useAppendStr = (strlen($string) > intval($length))? true:false;
        $truncated_str = substr($string,0,$length);
        $truncated_str .= ($useAppendStr)? $appendStr:"";
        return $truncated_str;
    }

    private function _validateData( $object ) {

        $errors = array();

        $billingAddress = $object->getBillingAddress();
        $shippingAddress = $object->getShippingAddress();

    	$billing_postcode = $billingAddress->getPostcode();
    	$billing_state = $billingAddress->getRegion();
    	$billing_telephone = $billingAddress->getTelephone();
    	$billing_city = $billingAddress->getCity();
    	$billing_street = $billingAddress->getStreet1();

        if( empty($billing_postcode) ) {
            $errors[] = "Billing Postcode is required";
        }
        if( empty($billing_state) ) {
            $errors[] = "Billing State is required";
        }
        if( empty($billing_telephone) ) {
            $errors[] = "Billing Phone is required";
        }
        if( empty($billing_city) ) {
            $errors[] = "Billing City/Suburb is required";
        }
        if( empty($billing_street) ) {
            $errors[] = "Billing Address is required";
        }

        if( !empty($shippingAddress) ) {
        	$shipping_postcode = $shippingAddress->getPostcode();
        	$shipping_state = $shippingAddress->getRegion();
        	$shipping_telephone = $shippingAddress->getTelephone();
        	$shipping_city = $shippingAddress->getCity();
        	$shipping_street = $shippingAddress->getStreet1();

            if( empty($shipping_postcode) ) {
                $errors[] = "Shipping Postcode is required";
            }
            if( empty($shipping_state) ) {
                $errors[] = "Shipping State is required";
            }
            if( empty($shipping_telephone) ) {
                $errors[] = "Shipping Phone is required";
            }
            if( empty($shipping_city) ) {
                $errors[] = "Shipping City/Suburb is required";
            }
            if( empty($shipping_street) ) {
                $errors[] = "Shipping Address is required";
            }
        }

        if( !empty($errors) && count($errors) ) {
            throw new InvalidArgumentException( "<br/>" . implode($errors, '<br/>') );
        }
    }
}