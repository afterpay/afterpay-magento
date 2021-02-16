<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/**
 * Class Afterpay_Afterpay_Model_Api_Adapters_Adapterv1
 *
 * Building API requests and parsing API responses.
 */
class Afterpay_Afterpay_Model_Api_Adapters_Adapterv1
{

    /**
     * @return Afterpay_Afterpay_Model_Api_Router
     */
    public function getApiRouter()
    {
        return Mage::getModel('afterpay/api_routers_routerv1');
    }

    public function buildOrderTokenRequest($object, $override = array(), $afterPayPaymentTypeCode = NULL)
    {
        // TODO: Add warning log in case if rounding changes amount, because it's potential problem
        $precision = 2;

        $this->_validateData($object);

        $data = $object->getData();

        $billingAddress  = $object->getBillingAddress();

        $taxTotal = 0;

        $params['consumer'] = array(
            'email'         => (string)$object->getCustomerEmail(),
            'givenNames'    => $object->getCustomerFirstname() ? (string)$object->getCustomerFirstname() : $billingAddress->getFirstname(),
            'surname'       => $object->getCustomerLastname() ? (string)$object->getCustomerLastname() : $billingAddress->getLastname(),
            'phoneNumber'   => substr( (string)$billingAddress->getTelephone(), 0, 32 )
        );

        $params['items'] = array();

        foreach ($object->getAllVisibleItems() as $item) {
            /** @var Mage_Sales_Model_Order_Item $orderItem */
            $params['items'][] = array(
                'name'     => (string)$item->getName(),
                'sku'      => $this->_truncateString( (string)$item->getSku() ),
                'quantity' => (int)$item->getQty(),
                'price'    => array(
                    'amount'   => number_format((float)$item->getPriceInclTax(), $precision, '.', ''),
                    'currency' => (string)Mage::app()->getStore()->getCurrentCurrencyCode()
                )
            );

            //get the total discount amount
            $discount_amount = $item->getDiscountAmount();

            if ( !empty($discount_amount) && round((float)$discount_amount, $precision) > 0 ) {

                $discount_name = (string)$object->getCouponCode();

                if( empty($discount_name) || strlen(trim($discount_name)) == '' ) {
                    $discount_name = 'Discount:';
                }

                $params['discounts'][] =  array(
                    'displayName'   =>  substr( $discount_name . ' - ' . (string)$item->getName(), 0, 128 ),
                    'amount'        =>  array(
                                            'amount'   => number_format((float)$item->getDiscountAmount(), $precision, '.', ''),
                                            'currency' => (string)Mage::app()->getStore()->getCurrentCurrencyCode()
                                        ),
                );
            }

            //get the total discount amount
            $taxTotal += $item->getTaxAmount();
        }

        if( isset($taxTotal) && round((float)$taxTotal, $precision) > 0 ) {
            $params['taxAmount'] = array(
                'amount'   => number_format((float)$taxTotal, $precision, '.', ''),
                'currency' => (string)Mage::app()->getStore()->getCurrentCurrencyCode()
            );
        }

        $params['billing'] = array(
            'name'          => (string)$billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
            'line1'         => (string)$billingAddress->getStreet1(),
            'line2'         => (string)$billingAddress->getStreet2(),
            'suburb'        => (string)$billingAddress->getCity(),
            'postcode'      => (string)$billingAddress->getPostcode(),
            'state'         => (string)$billingAddress->getRegion(),
            'phoneNumber'   => (string)$billingAddress->getTelephone(),
            'countryCode'   => (string)$billingAddress->getCountry(),
        );

        if (!$object->isVirtual())
        {
            $shippingAddress = $object->getShippingAddress();
            $shippingMethods  = $shippingAddress->getShippingRatesCollection();

            foreach ($shippingMethods as $method) {
                $params['courier'] = array(
                    'name'          => substr($method->getMethodTitle() . " " . $method->getCarrierTitle(), 0, 128),
                    'priority'      => "STANDARD",
                );
            }

            if ($shippingAddress->getShippingInclTax()) {
                $params['shippingAmount'] = array(
                    'amount'   => number_format((float)$shippingAddress->getShippingInclTax(), $precision, '.', ''), // with tax
                    'currency' => (string)Mage::app()->getStore()->getCurrentCurrencyCode()
                );
            }

            if( !empty( $shippingAddress ) ) {
                if( !empty( $shippingAddress->getStreet1() ) ) {
                    $params['shipping'] = array(
                        'name'          => (string)$shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname(),
                        'line1'         => (string)$shippingAddress->getStreet1(),
                        'line2'         => (string)$shippingAddress->getStreet2(),
                        'suburb'        => (string)$shippingAddress->getCity(),
                        'postcode'      => (string)$shippingAddress->getPostcode(),
                        'state'         => (string)$shippingAddress->getRegion(),
                        'phoneNumber'   => (string)$shippingAddress->getTelephone(),
                        'countryCode'   => (string)$shippingAddress->getCountry(),
                    );
                }
            }
        }

        $params['totalAmount'] = array(
            'amount'   => number_format((float)$object->getGrandTotal(), $precision, '.', ''),
            'currency' => (string)Mage::app()->getStore()->getCurrentCurrencyCode(),
        );

        $params['merchant'] = array(
            'redirectConfirmUrl'    => $this->getApiRouter()->getConfirmOrderUrl(),
            'redirectCancelUrl'     => $this->getApiRouter()->getCancelOrderUrl(),
        );

        if( !empty($object) && $object->getReservedOrderId() ) {
            $params['merchantReference'] = (string)$object->getReservedOrderId();
        }

        return $params;
    }

    public function buildExpressOrderTokenRequest($object)
    {
        $precision = 2;

        $params = array(
            'mode' => 'express',
            'totalAmount' => array(
                'amount' => round((float)$object->getSubtotalWithDiscount(), $precision),
                'currency' => (string)Mage::app()->getStore()->getCurrentCurrencyCode(),
            ),
            'merchant' => array(
                'redirectConfirmUrl'    => $this->getApiRouter()->getConfirmOrderUrl(),
                'redirectCancelUrl'     => $this->getApiRouter()->getCancelOrderUrl(),
            ),
            'merchantReference' => (string)$object->getReservedOrderId(),
            'items' => array(),
            'discounts' => array(),
        );

        foreach ($object->getAllVisibleItems() as $item) {
            /** @var Mage_Sales_Model_Order_Item $item */
            $params['items'][] = array(
                'name'     => (string)$item->getName(),
                'sku'      => $this->_truncateString( (string)$item->getSku() ),
                'quantity' => (int)$item->getQty(),
                'price'    => array(
                    'amount'   => round((float)$item->getPriceInclTax(), $precision),
                    'currency' => (string)Mage::app()->getStore()->getCurrentCurrencyCode()
                )
            );
            //get the total discount amount
            $discount_amount = $item->getDiscountAmount();
            if ( !empty($discount_amount) && round((float)$discount_amount, $precision) > 0 ) {
                $discount_name = (string)$object->getCouponCode();
                if( empty($discount_name) || strlen(trim($discount_name)) == '' ) {
                    $discount_name = 'Discount:';
                }
                $params['discounts'][] =  array(
                    'displayName'   =>  substr( $discount_name . ' - ' . (string)$item->getName(), 0, 128 ),
                    'amount'        =>  array(
                                            'amount'   => round((float)$item->getDiscountAmount(), $precision),
                                            'currency' => (string)Mage::app()->getStore()->getCurrentCurrencyCode()
                                        ),
                );
            }
        }
        return $params;
    }

    public function buildDirectCaptureRequest($orderToken, $merchantOrderId, $quote)
    {
        if ($quote->getData('afterpay_express_checkout') ) {
            $params = array(
                'amount' => json_decode($quote->getData('afterpay_express_amount'), true),
                'isCheckoutAdjusted' => true,
                'shipping' => json_decode($quote->getData('afterpay_express_shipping'), true),
            );
        } else {
            $params = array();
        }

        $params['token'] = $orderToken;
        $params['merchantReference'] = $merchantOrderId;
        $params['webhookEventUrl'] = '';

        return $params;
    }

    /**
     * Get the URL for Refunds API Ver 1
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
                                'amount'    => number_format($amount, 2, '.', ''),
                                'currency'  => $payment->getOrder()->getOrderCurrencyCode(),
                            );

        $params['merchantReference'] = NULL;

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

    	$billing_postcode = $billingAddress->getPostcode();
    	$billing_telephone = $billingAddress->getTelephone();
    	$billing_city = $billingAddress->getCity();
    	$billing_street = $billingAddress->getStreet1();

        if( empty($billing_postcode) ) {
            $errors[] = "Billing Postcode is required";
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

        if (!$object->isVirtual()) {
            $shippingAddress = $object->getShippingAddress();

        	$shipping_postcode = $shippingAddress->getPostcode();
        	$shipping_telephone = $shippingAddress->getTelephone();
        	$shipping_city = $shippingAddress->getCity();
        	$shipping_street = $shippingAddress->getStreet1();

            if( empty($shipping_postcode) ) {
                $errors[] = "Shipping Postcode is required";
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
