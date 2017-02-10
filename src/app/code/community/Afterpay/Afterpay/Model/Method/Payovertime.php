<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */
class Afterpay_Afterpay_Model_Method_Payovertime extends Afterpay_Afterpay_Model_Method_Base
{
    /**
     * Constant variable
     */
    const CODE = 'afterpaypayovertime';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::CODE;
    protected $_isGateway    = true;
    protected $_canAuthorize = true;
    protected $_canCapture   = true;

    /**
     * Info and form blocks
     *
     * @var string
     */
    protected $_formBlockType = 'afterpay/form_payovertime';
    protected $_infoBlockType = 'afterpay/info';

    /**
     * Payment type code according to Afterpay API documentation.
     *
     * @var string
     */
    protected $afterPayPaymentTypeCode = 'PBI';

    /**
     * flag if we need to run payment initialize while order place
     *
     * @return bool
     */
    public function isInitializeNeeded()
    {
        if ($this->getConfigPaymentAction() == self::ACTION_AUTHORIZE_CAPTURE) {
            return false;
        }
        return $this->_isInitializeNeeded;
    }

    /**
     * Capture the payment.
     *
     * Basically, this capture function is connecting API and check between session and Afterpay details
     * To make sure it is NOT fraud request
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    function capture(Varien_Object $payment, $amount)
    {

            $session = Mage::getSingleton('checkout/session');
            $quote = $session->getQuote();
            //$payment = $session->getQuote()->getPayment();
         
            $orderToken = $payment->getAfterpayToken();
            $reserved_order_id = $quote->getReservedOrderId();

            try {
                $data = Mage::getModel('afterpay/order')->directCapture( $orderToken, $reserved_order_id, $quote );
            }
            catch( Exception $e ) {
                $this->resetTransactionToken($quote);
                $this->resetPayment($payment);
              
                Mage::throwException(
                    Mage::helper('afterpay')->__( $e->getMessage() )
                );
            }

            $afterpayOrderId = $data->id;

            // save orderid to payment
            if ($payment) {
                $payment->setAfterpayOrderId($afterpayOrderId)->save();
                $quote->setAfterpayOrderId($afterpayOrderId)->save();
            }

            /**
             * Validation to check between session and post request
             */
            // Check the order token being use
            if ($payment->getAfterpayToken() != $data->token) {

                $this->resetTransactionToken($quote);
                Mage::throwException(
                    Mage::helper('afterpay')->__('Afterpay gateway has rejected request. Invalid token.')
                );
            }

            // Check total amount
            // $amount = round((float)$data->totalAmount->amount, 2); // convert to original value
            // if (ceil($quote->getGrandTotal()) != ceil($amount)) {
            //     Mage::throwException(
            //         Mage::helper('afterpay')->__('Afterpay gateway has rejected request. Invalid amount.')
            //     );
            // }

            // Check order id
            if ($quote->getReservedOrderId() != $data->merchantReference) {

                $this->resetTransactionToken($quote);

                Mage::throwException(
                    Mage::helper('afterpay')->__('Afterpay gateway has rejected request. Detected fraud.')
                );
            }

            switch($data->status) {
                case Afterpay_Afterpay_Model_Method_Base::RESPONSE_STATUS_APPROVED:
                    $payment->setTransactionId($payment->getAfterpayOrderId())->save();
                    break;
                case Afterpay_Afterpay_Model_Method_Base::RESPONSE_STATUS_DECLINED:

                    $this->resetTransactionToken($quote);

                    Mage::throwException(
                        Mage::helper('afterpay')->__('Afterpay payment has been declined. Please use other payment method.')
                    );
                    break;
                case Afterpay_Afterpay_Model_Method_Base::RESPONSE_STATUS_PENDING:
                    $payment->setTransactionId($payment->getAfterpayOrderId())
                        ->setIsTransactionPending(true);
                    break;
                default:

                    $this->resetTransactionToken($quote);

                    Mage::throwException(
                        Mage::helper('afterpay')->__('Cannot find Afterpay payment. Please contact administrator.')
                    );
                    break;
            }

            return $this;
    }

    /**
     * Get Payment Review Status
     *
     * @return mixed
     */
    public function getPaymentReviewStatus()
    {
        return $this->getConfigData('payment_review_status');
    }


    /**
     * Resetting the token the session
     *
     * @return bool
     */
    public function resetTransactionToken($quote) {

        Mage::getSingleton("checkout/session")->getQuote()->getPayment()->setAfterpayToken(NULL)->save();

        return true;
    }


    /**
     * Resetting the payment in the capture step
     *
     * @return bool
     */
    public function resetPayment($payment) {

        $payment->setAfterpayToken(NULL)->save();

        return true;
    }
}
