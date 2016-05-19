<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Andrey Legayev <andrey@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */
class ApiAdapterDataProvider extends \PHPUnit_Framework_TestCase
{

    /**
     * @return array
     */
    public static function getEmptyPaymentTypeCodes()
    {
        return array(
            array(null),
            array(0),
            array('')
        );
    }

    /**
     * @return array
     */
    public static function getOrdersTestDataSet()
    {
        $paymentTypes = array(
            'Pad'    => 'PAD',
            'Pbi'    => 'PBI',
            'PayNow' => 'PAY_NOW'
        );

        $customerData = array(
            'Normal' => array(
                'customer_email'     => 'test-customer@example.com',
                'customer_firstname' => 'TestFirstname',
                'customer_lastname'  => 'TestLastname'
            ),
            'Null'   => array(
                'customer_email'     => 'test-customer@example.com',
                'customer_firstname' => null,
                'customer_lastname'  => null
            ),
        );

        $addresses = array(
            'Normal'  => array(
                'firstname' => 'TestFirstname',
                'lastname'  => 'TestLastname',
                'street1'   => '1 LaTrobe Street',
                'street2'   => 'Second Line of Address',
                'city'      => 'Melbourne',
                'postcode'  => '3000',
                'telephone' => '0434567890'
            ),
            'Integer' => array(
                'firstname' => 'TestFirstname',
                'lastname'  => 'TestLastname',
                'street1'   => '222 LaTrobe Street',
                'street2'   => '',
                'city'      => 'Melbourne',
                'postcode'  => 3000,
                'telephone' => 90434567890
            ),
            'Null'    => array(
                'firstname' => null,
                'lastname'  => null,
                'street1'   => null,
                'street2'   => null,
                'city'      => null,
                'postcode'  => null,
                'telephone' => null
            )
        );

        $ordersData = array(
            'Float'   => array(
                'created_at'          => '2014-12-14 15:05:33',
                'increment_id'        => '200000544-5',
                'order_currency_code' => 'AUD',
                'shipping_incl_tax'   => 5.57777,
                'discount_amount'     => 10.577777,
                'tax_amount'          => 7.977777,
                'subtotal'            => 79.27777,
                'grand_total'         => 84.777777,
                'items'               => array(
                    array(
                        'name'           => 'Test product',
                        'sku'            => 'test',
                        'qty_ordered'    => 2,
                        'price_incl_tax' => 39.67777777,
                    )
                ),
            ),
            'Integer' => array(
                'created_at'          => '2013-12-11 12:03:30',
                'increment_id'        => '100010544',
                'order_currency_code' => 'USD', // USD for test
                'shipping_incl_tax'   => 5, // all prices as integer numbers
                'discount_amount'     => 10,
                'tax_amount'          => 8,
                'subtotal'            => 80,
                'grand_total'         => 85,
                'items'               => array(
                    array(
                        'name'           => 'Test product - Size 5',
                        'sku'            => 'test/5',
                        'qty_ordered'    => 1,
                        'price_incl_tax' => 40,
                    ),
                    array(
                        'name'           => 'Test product - Size 7.5',
                        'sku'            => 'test/7.5',
                        'qty_ordered'    => 1,
                        'price_incl_tax' => 40,
                    ),
                ),
            ),
            'Empty'   => array(
                'created_at'          => '',
                'increment_id'        => '',
                'order_currency_code' => '',
                'shipping_incl_tax'   => 0,
                'discount_amount'     => 0,
                'tax_amount'          => 0,
                'subtotal'            => 0,
                'grand_total'         => 0,
                'items'               => array(
                    array(
                        'name'           => null,
                        'sku'            => null,
                        'qty_ordered'    => 1,
                        'price_incl_tax' => 0,
                    )
                ),
            ),
        );

        // getenerate test datasets
        $result = array();

        foreach ($paymentTypes as $paymentKey => $payment) {
            $key = array($paymentKey . 'Payment');

            foreach ($customerData as $customerDataKey => $customer) {
                $key[1] = $customerDataKey . 'Customer';

                foreach ($ordersData as $ordersDataKey => $order) {
                    $key[2] = $ordersDataKey . 'Order';

                    foreach ($addresses as $billingAddressKey => $billingAddress) {
                        $key[3] = $billingAddressKey . 'BillingAddress';

                        foreach ($addresses as $shippingAddressKey => $shippingAddress) {
                            $key[4] = $shippingAddressKey . 'ShippingAddress';

                            $result[join(';', $key)] = array(
                                $payment,
                                $customer,
                                $order,
                                $billingAddress,
                                $shippingAddress
                            );
                        }
                    }
                }
            }
        }

        return $result;
    }


    /**
     * @return array
     */
    public static function getEmptyDataForShippedAPI()
    {
        return array(
            array(null, null),
        );
    }

    /**
     * @return array
     */
    public static function getDataForShippedAPI()
    {
        return array(
            array('123123123', 'test courier'),
            array('', ''),
            array(123123123, 1)

        );
    }

}
