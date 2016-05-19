<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Andrey Legayev <andrey@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */
class MockFactory extends \PHPUnit_Framework_TestCase
{
    /**
     * Build order mock
     *
     * @param array $data Order data
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    public function getOrderMock($data = array())
    {
        $mock = $this->getMockBuilder('Mage_Sales_Model_Order')
            ->setMethods(array(
                'getData', 'getCreatedAt', 'getIncrementId', 'getAllVisibleItems',
                'getOrderCurrencyCode', 'getGrandTotal', 'getShippingInclTax',
                'getBillingAddress', 'getShippingAddress',
                'getCustomerEmail', 'getCustomerFirstname', 'getCustomerLastname',
            ))
            ->getMock();

        $mock->expects($this->any())->method('getData')->will($this->returnValue($data));

        if (!empty($data)) {
            $mock->expects($this->any())->method('getCreatedAt')->will($this->returnValue($data['created_at']));
            $mock->expects($this->any())->method('getIncrementId')->will($this->returnValue($data['increment_id']));
            $mock->expects($this->any())->method('getCustomerEmail')->will($this->returnValue($data['customer_email']));
            $mock->expects($this->any())->method('getCustomerFirstname')->will($this->returnValue($data['customer_firstname']));
            $mock->expects($this->any())->method('getCustomerLastname')->will($this->returnValue($data['customer_lastname']));
            $mock->expects($this->any())->method('getCustomerLastname')->will($this->returnValue($data['customer_lastname']));
            $mock->expects($this->any())->method('getCustomerLastname')->will($this->returnValue($data['customer_lastname']));
            $mock->expects($this->any())->method('getShippingInclTax')->will($this->returnValue($data['shipping_incl_tax']));
            $mock->expects($this->any())->method('getGrandTotal')->will($this->returnValue($data['grand_total']));
            $mock->expects($this->any())->method('getOrderCurrencyCode')->will($this->returnValue($data['order_currency_code']));
        }

        if (isset($data['items'])) {
            $items = array();
            foreach ($data['items'] as $itemData) {
                $items[] = $this->getOrderItemMock($itemData);
            }
            $mock->expects($this->any())->method('getAllVisibleItems')->will($this->returnValue($items));
        }

        if (isset($data['billing_address'])) {
            $mock->expects($this->any())->method('getBillingAddress')
                ->will($this->returnValue($this->getOrderAddressMock($data['billing_address'])));
        }

        if (isset($data['shipping_address'])) {
            $mock->expects($this->any())->method('getShippingAddress')
                ->will($this->returnValue($this->getOrderAddressMock($data['shipping_address'])));
        }

        return $mock;
    }

    /**
     * Build order address mock
     *
     * @param array $data Address data
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    public function getOrderAddressMock($data = array())
    {
        // Create a stub for the Mage_Sales_Model_Order_Address class.
        $mock = $this->getMockBuilder('Mage_Sales_Model_Order_Address')
            ->setMethods(array(
                'getData', 'getTelephone',
                'getFirstname', 'getLastname',
                'getStreet1', 'getStreet2', 'getCity', 'getPostcode'

            ))
            ->getMock();

        $mock->expects($this->any())->method('getData')->will($this->returnValue($data));
        $mock->expects($this->any())->method('getTelephone')->will($this->returnValue($data['telephone']));
        $mock->expects($this->any())->method('getFirstname')->will($this->returnValue($data['firstname']));
        $mock->expects($this->any())->method('getLastname')->will($this->returnValue($data['lastname']));
        $mock->expects($this->any())->method('getStreet1')->will($this->returnValue($data['street1']));
        $mock->expects($this->any())->method('getStreet2')->will($this->returnValue($data['street2']));
        $mock->expects($this->any())->method('getCity')->will($this->returnValue($data['city']));
        $mock->expects($this->any())->method('getPostcode')->will($this->returnValue($data['postcode']));

        return $mock;
    }

    /**
     * Build Order Item mock
     *
     * @param array $data Order item data
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getOrderItemMock($data = array())
    {
        // Create a stub for the Mage_Sales_Model_Order_Address class.
        $mock = $this->getMockBuilder('Mage_Sales_Model_Order_Item')
            ->setMethods(array(
                'getData', 'getName', 'getSku', 'getQtyOrdered', 'getPriceInclTax'
            ))
            ->getMock();

        $mock->expects($this->any())->method('getData')->will($this->returnValue($data));
        $mock->expects($this->any())->method('getName')->will($this->returnValue($data['name']));
        $mock->expects($this->any())->method('getSku')->will($this->returnValue($data['sku']));
        $mock->expects($this->any())->method('getQtyOrdered')->will($this->returnValue($data['qty_ordered']));
        $mock->expects($this->any())->method('getPriceInclTax')->will($this->returnValue($data['price_incl_tax']));

        return $mock;
    }
}
