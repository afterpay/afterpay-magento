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
            
            $orderToken = $payment->getData('afterpay_token');
            $reserved_order_id = $quote->getReservedOrderId();

            // Check total amount
            if ( !empty($orderToken) && $this->getConfigPaymentAction() == self::ACTION_AUTHORIZE_CAPTURE ) {

                $data = Mage::getModel('afterpay/order')->getOrderByToken( $orderToken );

                /**
                 * Validation to check between session and post request
                 */
                if( !$data ) {

                    // Check the order token being use
                    $this->resetTransactionToken($quote);
            
                    Mage::helper('afterpay')->log( 
                        'Afterpay gateway has rejected request. Invalid token. ' .
                        ' Token Value: ' . $orderToken
                    );

                    Mage::throwException(
                        Mage::helper('afterpay')->__('Afterpay gateway has rejected request. Invalid token.')
                    );
                }
                else if( $reserved_order_id != $data->merchantReference ) {
                    
                    // Check order id
                    $this->resetTransactionToken($quote);
            
                    Mage::helper('afterpay')->log( 
                        'Afterpay gateway has rejected request. Incorrect merchant reference. ' .
                        ' Quote Value: ' . $reserved_order_id . 
                        ' Afterpay API: ' . $data->merchantReference
                    );

                    Mage::throwException(
                        Mage::helper('afterpay')->__('Afterpay gateway has rejected request. Incorrect merchant reference.')
                    );
                }
                else if( round($quote->getGrandTotal(), 2) != round($data->totalAmount->amount, 2) ) {

                    // Check the order amount
                    $this->resetTransactionToken($quote);
		    
                    Mage::helper('afterpay')->log( 
                        'Afterpay gateway has rejected request. Invalid amount. ' .
                        ' Quote Amount: ' . round($quote->getGrandTotal(), 2) . 
                        ' Afterpay API: ' . round($data->totalAmount->amount, 2)
                    );

                    Mage::throwException(
                        Mage::helper('afterpay')->__('Afterpay gateway has rejected request. Invalid amount.')
                    );
                }
            }

            try {
                $data = Mage::getModel('afterpay/order')->directCapture( $orderToken, $reserved_order_id, $quote );
            }
            catch( Exception $e ) {
                $this->resetTransactionToken($quote);
                $this->resetPayment($payment);
                
                Mage::helper('afterpay')->log( 'Direct Capture Failed: ' . $e->getMessage() );
              
                Mage::throwException(
                    Mage::helper('afterpay')->__( $e->getMessage() )
                );
            }


            if( !empty($data) && !empty($data->id) ) {
                $afterpayOrderId = $data->id;

                // save orderid to payment
                if ($payment) {
                    $payment->setData('afterpay_order_id', $afterpayOrderId)->save();
                    $quote->setData('afterpay_order_id', $afterpayOrderId)->save();
                }  
            }


            switch($data->status) {
                case Afterpay_Afterpay_Model_Method_Base::RESPONSE_STATUS_APPROVED:
                    $payment->setTransactionId($payment->getData('afterpay_order_id'))->save();
                    break;
                case Afterpay_Afterpay_Model_Method_Base::RESPONSE_STATUS_DECLINED:

                    $this->resetTransactionToken($quote);

                    Mage::throwException(
                        Mage::helper('afterpay')->__('Afterpay payment has been declined. Please use other payment method.')
                    );
                    break;
                case Afterpay_Afterpay_Model_Method_Base::RESPONSE_STATUS_PENDING:
                    $payment->setTransactionId($payment->getData('afterpay_order_id'))
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

        Mage::getSingleton("checkout/session")->getQuote()->getPayment()->setData('afterpay_token', NULL)->save();

        if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
            Mage::helper('afterpay')->storeCreditSessionUnset();
            Mage::helper('afterpay')->giftCardsSessionUnset();
        }

        return true;
    }


    /**
     * Resetting the payment in the capture step
     *
     * @return bool
     */
    public function resetPayment($payment) {

        $payment->setData('afterpay_token', NULL)->save();

        return true;
    }
}
