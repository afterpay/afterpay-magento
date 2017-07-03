<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Aferpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au)
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
        $shippingAddress = $object->getShippingAddress();
        $shippingMethods  = $shippingAddress->getShippingRatesCollection();

        $taxTotal = 0;

        $params['consumer'] = array(
            'email'         => (string)$object->getCustomerEmail(),
            'givenNames'    => (string)$object->getCustomerFirstname(),
            'surname'       => (string)$object->getCustomerLastname(),
            'phoneNumber'   => substr( (string)$billingAddress->getTelephone(), 0, 32 )
        );

        $params['items'] = array();

        //not sure what should go to priority, I guess it will say something in the description if it is Express
        foreach ($shippingMethods as $method) {
            $params['courier'] = array(
                'name'          => substr($method->getMethodTitle() . " " . $method->getCarrierTitle(), 0, 128),
                'priority'      => "STANDARD",
            );
        }

        foreach ($object->getAllVisibleItems() as $item) {
            /** @var Mage_Sales_Model_Order_Item $orderItem */
            $params['items'][] = array(
                'name'     => (string)$item->getName(),
                'sku'      => $this->_truncateString( (string)$item->getSku() ),
                'quantity' => (int)$item->getQty(),
                'price'    => array(
                    'amount'   => round((float)$item->getPriceInclTax(), $precision),
                    'currency' => (string)$data['store_currency_code']
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
                                            'currency' => (string)$data['store_currency_code'] 
                                        ),
                );
            }

            //get the total discount amount
            $taxTotal += $item->getTaxAmount();
        }

        if ($shippingAddress->getShippingInclTax()) {
            $params['shippingAmount'] = array(
                'amount'   => round((float)$shippingAddress->getShippingInclTax(), $precision), // with tax
                'currency' => (string)$data['store_currency_code']
            );
        }

        // $taxAmount = $shippingAddress->getData('tax_amount');
        if( isset($taxTotal) && round((float)$taxTotal, $precision) > 0 ) {
            $params['taxAmount'] = array(
                'amount'   => isset($taxTotal) ? round((float)$taxTotal, $precision) : 0,
                'currency' => (string)$data['store_currency_code']
            );
        }

        // $params['orderDetail']['subTotal'] = array(
        //     'amount'   => round((float)$data['subtotal'], $precision),
        //     'currency' => (string)$data['store_currency_code'],
        // );

        if( !empty($params['shipping']) ) {
            $params['shipping'] = array(
                'name'          => (string)$shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname(),
                'line1'         => (string)$shippingAddress->getStreet1(),
                'line2'         => (string)$shippingAddress->getStreet2(),
                'suburb'        => (string)$shippingAddress->getCity(),
                'postcode'      => (string)$shippingAddress->getPostcode(),
                'state'         => (string)$shippingAddress->getRegion(),
                'phoneNumber'   => (string)$shippingAddress->getTelephone(),
                // 'countryCode'   => 'AU',
                'countryCode'   => (string)$shippingAddress->getCountry(),
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
            // 'countryCode'   => 'AU',
            'countryCode'   => (string)$billingAddress->getCountry(),
        );

        $params['totalAmount'] = array(
            'amount'   => round((float)$object->getGrandTotal(), $precision),
            'currency' => (string)$data['store_currency_code'],
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

    public function buildDirectCaptureRequest($orderToken, $merchantOrderId)
    {
        $params['token'] = $orderToken;
        $params['merchantReference'] = $merchantOrderId;
        $params['webhookEventUrl'] = "";

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
            'tracking'      => is_null($trackingNumber) ? null : (string)$trackingNumber,
            'shippedAt'     => date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time())),
            'name'          => $courier,
        );
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
                                'amount'    => abs($amount), // Afterpay API Ver 1 requires a positive amount
                                'currency'  => $payment->getOrder()->getGlobalCurrencyCode(),
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