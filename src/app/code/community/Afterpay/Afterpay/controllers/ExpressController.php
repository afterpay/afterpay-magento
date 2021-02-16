<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/**
 * Class Afterpay_Afterpay_ExpressController
 *
 * Afterpay express checkout
*/

use Afterpay_Afterpay_Model_Method_Base as Afterpay_Base;

class Afterpay_Afterpay_ExpressController extends Mage_Core_Controller_Front_Action
{
    public function startAction()
    {
        $helper = Mage::helper('afterpay');
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        try {
            $logged_in = Mage::getSingleton('customer/session')->isLoggedIn();
            if ($logged_in) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER);
            } else {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
            }
            $quote->save();

            $token = Mage::getModel('afterpay/order')->startExpress($quote);
            $response = array(
                'success' => true,
                'token'  => $token,
            );
        }
        catch (Exception $e) {
            $helper->log($this->__('Error occur during process. %s. QuoteID=%s', $e->getMessage(), $quote->getId()), Zend_Log::ERR);

            $message = $helper->__('There was an error processing your order. %s', $e->getMessage());
            Mage::getSingleton('checkout/session')->addError($message);
            $response = array(
                'success'  => false,
                'message'  => $message,
                'redirect' => Mage::getUrl('checkout/cart'),
            );
        }

        $this->_prepareDataJSON($response);
    }

    /**
     * Get available shipping methods and their rates on address change
     */
    public function changeAction()
    {
        $result = array();
        $data = $this->getRequest()->getParams();
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $token = $quote->getPayment()->getData('afterpay_token');
        if ($this->_isCartEdited($token)) {
            $result = array(
                'error' => true,
                'message' => 'The shopping cart was edited during the checkout. Please close the popup and try again.'
            );
        }
        else {
            $quote->getShippingAddress()
                ->setCountryId($data['countryCode'])
                ->setCity($data['suburb'])
                ->setPostcode($data['postcode'])
                ->setRegionId($this->_getRegionId($data['state'], $data['countryCode']))
                ->setRegion($data['state'])
                ->setCollectShippingRates(true);
            $quote->collectTotals()->save();

            $shipping = $quote->getShippingAddress();
            $rates = array_map(function($item){
                return $item->toArray();
            }, $shipping->getAllShippingRates());

            $currency = (string)Mage::app()->getStore()->getCurrentCurrencyCode();
            $subtotal = $quote->getSubtotalWithDiscount();
            $max = Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Base::API_MAX_ORDER_TOTAL_FIELD);

            foreach ($rates as $rate) {
                $total = $subtotal + $rate['price'];
                // Only shipping options with a valid order amount will be returned
                if ($total <= $max) {
                    $result[] = array(
                        'id' => $rate['code'],
                        'name' => $rate['carrier_title'],
                        'description' => $rate['method_title'],
                        'shippingAmount' => array(
                            'amount' => number_format($rate['price'], 2, '.', ''),
                            'currency' => $currency
                        ),
                        'orderAmount' => array(
                            'amount' => number_format($total, 2, '.', ''),
                            'currency' => $currency
                        ),
                    );
                }
            }

            if (empty($result)) {
                $result = array(
                    'error' => true,
                    'message' => 'Shipping is unavailable for this address, or all options exceed Afterpay order limit.'
                );
            }
        }

        $this->_prepareDataJSON($result);
    }

    public function confirmAction()
    {
        $helper = Mage::helper('afterpay');
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $data = $this->getRequest()->getParams();

        try {
            $order = Mage::getModel('afterpay/order')->getOrderByToken($data['orderToken']);
            $order = json_decode(json_encode($order), true);

            if ($this->_isCartEdited($data['orderToken'])) {
                Mage::getSingleton('core/session')->addError('The shopping cart was edited during the checkout. Please try again.');
                $quote->getPayment()->setData('afterpay_token', NULL)->save();
                $this->_redirectUrl(Mage::helper('checkout/url')->getCartUrl());
                return;
            }

            $logged_in = Mage::getSingleton('customer/session')->isLoggedIn();
            if ($logged_in) {
                $customer = Mage::getSingleton('customer/session')->getCustomer();
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER)
                    ->setCustomer($customer);
            } else {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST)
                    ->setCustomerId(NULL)
                    ->setCustomerIsGuest(TRUE)
                    ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)
                    ->setCustomerFirstname($order['consumer']['givenNames'])
                    ->setCustomerLastname($order['consumer']['surname'])
                    ->setCustomerEmail($order['consumer']['email']);
            }

            $fullName = explode(' ', $order['shipping']['name']);
            $lastName = array_pop($fullName);
            if (empty($fullName)) {
                $firstName = $lastName; // if $order['shipping']['name'] contains only one word
            } else {
                $firstName = implode(' ', $fullName);
            }

            $billing = $quote->getBillingAddress();
            $billing
                ->setFirstname($firstName)
                ->setLastname($lastName)
                ->setEmail($order['consumer']['email'])
                ->setTelephone($order['shipping']['phoneNumber'])
                ->setStreet(array(
                    $order['shipping']['line1'],
                    isset($order['shipping']['line2'])?$order['shipping']['line2']:null
                ))
                ->setCity($order['shipping']['suburb'])
                ->setRegion($order['shipping']['state'])
                ->setRegionId($this->_getRegionId($order['shipping']['state'], $order['shipping']['countryCode']))
                ->setPostcode($order['shipping']['postcode'])
                ->setCountryId($order['shipping']['countryCode']);

            if (!$quote->isVirtual()) {
                $shipping = $quote->getShippingAddress();
                $shipping
                    ->setFirstname($firstName)
                    ->setLastname($lastName)
                    ->setEmail($order['consumer']['email'])
                    ->setTelephone($order['shipping']['phoneNumber'])
                    ->setStreet(array(
                        $order['shipping']['line1'],
                        isset($order['shipping']['line2'])?$order['shipping']['line2']:null
                    ))
                    ->setCity($order['shipping']['suburb'])
                    ->setRegion($order['shipping']['state'])
                    ->setRegionId($this->_getRegionId($order['shipping']['state'], $order['shipping']['countryCode']))
                    ->setPostcode($order['shipping']['postcode'])
                    ->setCountryId($order['shipping']['countryCode'])
                    ->setSameAsBilling(1)
                    ->setShippingMethod($order['shippingOptionIdentifier']);
                $shipping->setPaymentMethod(Afterpay_Afterpay_Model_Method_Payovertime::CODE);
            } else {
                $billing->setPaymentMethod(Afterpay_Afterpay_Model_Method_Payovertime::CODE);
            }

            $payment = $quote->getPayment();
            $payment->setData('afterpay_token', $data['orderToken']);
            $payment->setMethod(Afterpay_Afterpay_Model_Method_Payovertime::CODE)
                ->save();
            $payment->importData(array(
                'method' => Afterpay_Afterpay_Model_Method_Payovertime::CODE,
                'checks' => Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                    | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                    | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL
            ));

            $quote
                ->setData('afterpay_express_checkout', true)
                ->setData('afterpay_express_amount', json_encode($order['totalAmount']))
                ->setData('afterpay_express_shipping', json_encode($order['shipping']))
                ->save();

            if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
    	    	$quote = $helper->storeCreditCapture($quote);
		        $quote->save();
                $quote = $helper->giftCardsCapture($quote);
		        $quote->save();
    	    }

            $helper->log($this->__('Afterpay Payment Gateway Confirmation. QuoteID=%s ReservedOrderID=%s',$quote->getId(), $quote->getReservedOrderId()), Zend_Log::NOTICE);

            $this->_forward('placeOrder');
        }
        catch (Exception $e) {
            $response = array(
                'success' => false,
                'message'  => $e->getMessage(),
            );
            $this->_prepareDataJSON($response);
        }
    }

    /**
     * Place order to Magento
     */
    public function placeOrderAction()
    {
        $helper = Mage::helper('afterpay');
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        try {
            // Debug log
            $helper->log(
                $this->__(
                    'Creating order in Magento. AfterpayOrderId=%s QuoteID=%s ReservedOrderID=%s',
                    $quote->getData('afterpay_order_id'),
                    $quote->getId(),
                    $quote->getReservedOrderId()
                ),
                Zend_Log::NOTICE
            );

            // Placing order using Afterpay
            $placeOrder = Mage::getModel('afterpay/order')->place();

            if ($placeOrder) {

        	    //process the Store Credit on Orders
                if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
            		$helper->storeCreditPlaceOrder();
            		$helper->giftCardsPlaceOrder();
            	}

                // Debug log
                $helper->log(
                    $this->__(
                        'Order successfully created. Redirecting to success page. AfterpayOrderId=%s QuoteID=%s ReservedOrderID=%s',
                        $quote->getData('afterpay_order_id'),
                        $quote->getId(),
                        $quote->getReservedOrderId()
                    ),
                    Zend_Log::NOTICE
                );

                $quote->save();
            }

            // Redirect to success page
            $this->_redirect('checkout/onepage/success');
        } catch (Exception $e) {
            // Debug log
            $helper->log(
                $this->__(
                    'Order creation failed. %s. AfterpayOrderId=%s QuoteID=%s ReservedOrderID=%s Stack Trace=%s',
                    $e->getMessage(),
                    $quote->getData('afterpay_order_id'),
                    $quote->getId(),
                    $quote->getReservedOrderId(),
		            $e->getTraceAsString()
                ),
                Zend_Log::ERR
            );
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $quote->getPayment()->setData('afterpay_token', NULL)->save();

            // Afterpay redirect
            $this->_redirectUrl(Mage::helper('checkout/url')->getCartUrl());
        }
    }

    /**
     * Prepare JSON formatted data for response to client
     *
     * @param $response
     * @return Zend_Controller_Response_Abstract
     */
    protected function _prepareDataJSON($response)
    {
        $this->getResponse()->setHeader('Content-type', 'application/json', true);
        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    protected function _getRegionId($state, $countryCode)
    {
        $region = Mage::getModel('directory/region')->loadByName($state, $countryCode);
        if (!$region->getId()) {
            $region = Mage::getModel('directory/region')->loadByCode($state, $countryCode);
        }
        return $region->getId();
    }

    protected function _isCartEdited($token)
    {
        $order = Mage::getModel('afterpay/order')->getOrderByToken($token);
        $order = json_decode(json_encode($order), true);

        if (empty($order['errorCode'])) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $now = Mage::getModel('afterpay/api_adapters_adapterv1')->buildExpressOrderTokenRequest($quote);

            if ($now['items'] == $order['items'] &&
                $now['discounts'] == $order['discounts'])
            {
                return false;
            }
        }

        return true;
    }
}
