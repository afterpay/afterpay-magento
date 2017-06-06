<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Andrey Legayev <andrey@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */
class ApiAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MockFactory
     */
    protected $mockFactory;

    protected function setUp()
    {
        parent::setUp();
        $this->mockFactory = new MockFactory();
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testNullOrder()
    {
        $adapter = new Afterpay_Afterpay_Model_Api_Adapter();
        $adapter->buildOrderTokenRequest(null, null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @dataProvider ApiAdapterDataProvider::getEmptyPaymentTypeCodes
     */
    public function testEmptyPaymentTypeCode($paymentTypeCode)
    {
        $order = $this->mockFactory->getOrderMock();

        $adapter = new Afterpay_Afterpay_Model_Api_Adapter();
        $adapter->buildOrderTokenRequest($order, $paymentTypeCode);
    }

    /**
     * @dataProvider ApiAdapterDataProvider::getOrdersTestDataSet
     */
    public function testBuildingOrderTokenRequest($paymentTypeCode, $customerData, $orderData, $billingAddress, $shippingAddress)
    {
        $orderData = array_merge(
            $orderData,
            $customerData,
            array(
                'billing_address'  => $billingAddress,
                'shipping_address' => $shippingAddress
            )
        );

        $orderMock = $this->mockFactory->getOrderMock($orderData);

        $adapter = new Afterpay_Afterpay_Model_Api_Adapter();
        $request = $adapter->buildOrderTokenRequest($orderMock, $paymentTypeCode);

        // validate request data
        $this->assertTrue(is_array($request), 'Token request must be array');

        // validate payment type code
        $this->assertTrue(is_string($request['paymentType']), 'Payment type should be a string');
        $this->assertEquals($paymentTypeCode, $request['paymentType'], 'Payment tyme is wrong');

        // validate customer data
        $this->assertTrue(is_array($request['consumer']), '"consumer" data should be array');

        $this->assertTrue(is_string($request['consumer']['givenNames']), 'Given name should be a string');
        $this->assertEquals($customerData['customer_firstname'], $request['consumer']['givenNames'], 'Given name is wrong');

        $this->assertTrue(is_string($request['consumer']['surname']), 'Customer surname should be a string');
        $this->assertEquals($customerData['customer_lastname'], $request['consumer']['surname'], "Customer surname is wrong");

        $this->assertTrue(is_string($request['consumer']['mobile']), 'Customer mobile should be a string');
        $this->assertEquals($billingAddress['telephone'], $request['consumer']['mobile'], 'Customer mobile is wrong');

        // validate order detail data
        $this->assertTrue(is_array($request['orderDetail']), 'Order detail should be array');
        $this->assertTrue(is_int($request['orderDetail']['merchantOrderDate']), 'orderDetail/merchantOrderDate should be interer value');
        $this->assertEquals(strtotime($orderData['created_at']) * 1000, $request['orderDetail']['merchantOrderDate'], 'orderDetail/merchantOrderDate value is wrong');
        $this->assertTrue(is_string($request['orderDetail']['merchantOrderId']), 'orderDetail/merchantOrderId should be string value');
        $this->assertEquals($orderData['increment_id'], $request['orderDetail']['merchantOrderId'], 'orderDetail/merchantOrderId value is wrong');
        $this->assertTrue(is_string($request['orderDetail']['shippingPriority']), 'orderDetail/shippingPriority should be string value');
        $this->assertEquals('STANDARD', $request['orderDetail']['shippingPriority'], 'orderDetail/shippingPriority value is wrong');

        // validate totals
        if ($orderData['shipping_incl_tax'] > 0) {
            $this->assertTotalIsValid($orderData['shipping_incl_tax'], $orderData['order_currency_code'], $request['orderDetail']['shippingCost'], 'orderDetail/shippingCost');
        } else {
            $this->assertFalse(isset($request['orderDetail']['shippingCost']), 'Shipping should not be included into request if there is equals to zero');
        }

        if ($orderData['discount_amount'] > 0) {
            $this->assertEquals('Discount', $request['orderDetail']['discountType'], 'orderDetail/discountType value is wrong');
            $this->assertTotalIsValid($orderData['discount_amount'], $orderData['order_currency_code'], $request['orderDetail']['discount'], 'orderDetail/discount');
        } else {
            $this->assertFalse(isset($request['orderDetail']['discount']), 'Disount data should not be included into request if there is no discount');
        }

        $this->assertTotalIsValid($orderData['tax_amount'], $orderData['order_currency_code'], $request['orderDetail']['includedTaxes'], 'orderDetail/includedTaxes');
        $this->assertTotalIsValid($orderData['subtotal'], $orderData['order_currency_code'], $request['orderDetail']['subTotal'], 'orderDetail/subTotal');
        $this->assertTotalIsValid($orderData['grand_total'], $orderData['order_currency_code'], $request['orderDetail']['orderAmount'], 'orderDetail/orderAmount');

        // validate addresses
        $this->assertAddressIsValid($orderData['billing_address'], $request['orderDetail']['billingAddress'], 'orderDetail/billingAddress');
        $this->assertAddressIsValid($orderData['shipping_address'], $request['orderDetail']['shippingAddress'], 'orderDetail/shippingAddress');

        // validate order items
        $this->assertTrue(is_array($request['orderDetail']['items']), 'orderDetail/items should be array');
        foreach ($request['orderDetail']['items'] as $orderItemIndex => $orderItem) {
            $this->assertOrderItemIsValid($orderData['items'][$orderItemIndex], $orderData['order_currency_code'], $orderItem, 'orderDetail/items[' . $orderItemIndex . ']');
        }
    }

    protected function assertTotalIsValid($expectedAmount, $expectedCurrency, $actualData, $entityName)
    {
        $this->assertTrue(is_array($actualData), $entityName . ' data should be array');

        $this->assertTrue(is_float($actualData['amount']), $entityName . ' amount should be float');

        $roundedAmount = round($expectedAmount, 2);
        $precision     = 1E-10;
        $this->assertLessThan($precision, abs($roundedAmount - $actualData['amount']), $entityName . ' amount is wrong (' . $actualData['amount'] . '). Amount should be rounded to 2 digits after the decimal point (' . $roundedAmount . ')');

        $this->assertTrue(is_string($actualData['currency']), $entityName . ' currency should be a string');
        $this->assertEquals($expectedCurrency, $actualData['currency'], $entityName . ' currency is invalid');
    }

    protected function assertAddressIsValid($expectedData, $actualData, $entityName)
    {
        $this->assertTrue(is_array($actualData), $entityName . ' data should be array');

        $this->assertTrue(is_string($actualData['name']), $entityName . ' name should be a string');
        $this->assertEquals($expectedData['firstname'] . ' ' . $expectedData['lastname'], $actualData['name'], $entityName . ' name is wrong');

        $this->assertTrue(is_string($actualData['address1']), $entityName . ' address1 should be a string');
        $this->assertEquals($expectedData['street1'], $actualData['address1'], $entityName . ' address1 is wrong');

        $this->assertTrue(is_string($actualData['address2']), $entityName . ' address2 should be a string');
        $this->assertEquals($expectedData['street2'], $actualData['address2'], $entityName . ' address2 is wrong');

        $this->assertTrue(is_string($actualData['suburb']), $entityName . ' suburb should be a string');
        $this->assertEquals($expectedData['city'], $actualData['suburb'], $entityName . ' suburb is wrong');

        $this->assertTrue(is_string($actualData['postcode']), $entityName . ' postcode should be a string');
        $this->assertEquals($expectedData['postcode'], $actualData['postcode'], $entityName . ' postcode is wrong');
    }

    protected function assertOrderItemIsValid($expectedData, $expectedCurrency, $actualData, $entityName)
    {
        $this->assertTrue(is_array($actualData), $entityName . ' data should be array');

        $this->assertTrue(is_string($actualData['sku']), $entityName . ' sku should be a string');
        $this->assertEquals($expectedData['sku'], $actualData['sku'], $entityName . ' sku is wrong');

        $this->assertTrue(is_string($actualData['name']), $entityName . ' name should be a string');
        $this->assertEquals($expectedData['name'], $actualData['name'], $entityName . ' name is wrong');

        $this->assertTrue(is_int($actualData['quantity']), $entityName . ' quantity should be integer');
        $this->assertEquals($expectedData['qty_ordered'], $actualData['quantity'], $entityName . ' quantity is wrong');

        $this->assertTotalIsValid($expectedData['price_incl_tax'], $expectedCurrency, $actualData['price'], $entityName . '/price');
    }

    /**
     * @dataProvider ApiAdapterDataProvider::getEmptyDataForShippedAPI
     */
    public function testEmptyDataForShippedAPI($trackingNumber, $courier)
    {
        $adapter = new Afterpay_Afterpay_Model_Api_Adapter();
        $request = $adapter->buildSetShippedRequest($trackingNumber, $courier);

        // validate tracking number
        $this->assertNull($trackingNumber, $request['trackingNumber'], 'Tracking number should be NULL');

        // validate courier
        $this->assertNull($courier, $request['courier'], 'Courier should be NULL');
    }

    /**
     * @dataProvider ApiAdapterDataProvider::getDataForShippedAPI
     */
    public function testSetShippedApi($trackingNumber, $courier)
    {
        $adapter = new Afterpay_Afterpay_Model_Api_Adapter();
        $request = $adapter->buildSetShippedRequest($trackingNumber, $courier);

        // validate tracking number
        $this->assertTrue(is_string($request['trackingNumber']), 'tracking number should be a string');
        $this->assertEquals($trackingNumber, $request['trackingNumber'], 'Tracking number is wrong');

        // validate courier
        $this->assertTrue(is_string($request['courier']), 'courier should be a string');
        $this->assertEquals($courier, $request['courier'], 'courier is wrong');

    }
}
