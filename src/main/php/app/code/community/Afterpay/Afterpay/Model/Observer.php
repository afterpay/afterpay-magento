<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2014 VEN Commerce Ltd (http://www.ven.com)
 */
class Afterpay_Afterpay_Model_Observer
{
    /* Limit of orders which can be processed in one cron job run */
    const ORDERS_PROCESSING_LIMIT = 50;

    /* Limit of requests which will processed in one cron job run */
    const SET_SHIPPED_API_REQUESTS_LIMIT = 50;

    /* Limit of errors count before track ID is deleted from processing queue */
    const SET_SHIPPED_API_QUEUE_ERRORS_LIMIT = 3;

    /**
     * Cron job to check payment status of Payment Review orders
     *
     * @param Mage_Cron_Model_Schedule $schedule
     */
    public function fetchPaymentReviewPaymentStatus(Mage_Cron_Model_Schedule $schedule)
    {
        /** @var Mage_Sales_Model_Resource_Order_Collection|Mage_Sales_Model_Order[] $collection */
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->join(
            array('p' => 'sales/order_payment'),
            'main_table.entity_id = p.parent_id',
            array('method', 'afterpay_token', 'afterpay_order_id', 'afterpay_fetched_at')
        );
        $collection->addFieldToFilter(
            array('main_table.state', 'main_table.state'),
            array(
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW
            ))
            ->addFieldToFilter('p.method',
                array('like' => 'afterpay%')
            );

        $expiration = (int)Mage::getStoreConfig('afterpay/cron/fetch_payment_info_interval');
        $collection->addFieldToFilter(
            array('afterpay_fetched_at', 'afterpay_fetched_at'),
            array(
                array('null' => null),
                array('lt' => new Zend_Db_Expr("DATE_SUB( '" . now() . "', INTERVAL " . intval($expiration) . " SECOND )"))
            )
        );

        // set order by fetch info date and order update date
        $collection->setOrder('p.afterpay_fetched_at', Varien_Data_Collection::SORT_ORDER_ASC);
        $collection->setOrder('main_table.updated_at', Varien_Data_Collection::SORT_ORDER_ASC);

        $collection->setPageSize(self::ORDERS_PROCESSING_LIMIT);

        $jobCode = $schedule->getJobCode();

        $helper = $this->helper();
        if (!$collection->count()) {
            $helper->log(sprintf('%s: There is no suitable orders to process', $jobCode), Zend_Log::DEBUG);
        }

        foreach ($collection as $order) {
            $this->fetchOrderPaymentStatus($order, $jobCode);
        }
    }

    /**
     * Cron job to re-check payment status of Processing orders (needed for pre-approved orders)
     *
     * @param Mage_Cron_Model_Schedule $schedule
     */
    public function checkPreApprovedOrdersPaymentStatus(Mage_Cron_Model_Schedule $schedule)
    {
        //TODO: Add ability to disable this feature
        $helper  = $this->helper();
        $jobCode = $schedule->getJobCode();

        $collection = $this->getPreApprovedOrderCollection();

        if (!$collection->count()) {
            $helper->log(sprintf('%s: There is no suitable orders to process', $jobCode), Zend_Log::DEBUG);
        }

        foreach ($collection as $order) {
            $payment = $order->getPayment();

            try {
                // Update afterpay_fetched_at to prevent re-checking orders in next 1 hour
                $payment->setData('afterpay_fetched_at', time());

                $paymentMethod = $payment->getMethodInstance();
                $paymentInfo   = $paymentMethod
                    ->setStore($order->getStoreId())
                    ->fetchTransactionInfo($payment, $payment->getLastTransId());

                //if payment status changed to PENDING - change order state & status to Payment review + add comment
                if (isset($paymentInfo['status']) && $paymentInfo['status'] == Afterpay_Afterpay_Model_Method_Base::RESPONSE_STATUS_PENDING) {
                    $message = Mage::helper('afterpay')->__(
                        'Afterpay: Payment status changed to PENDING, order has been automatically moved to payment review.'
                    );

                    // getting payment review order status from payment method configuration
                    $status = $paymentMethod->getConfigData('payment_review_status');
                    $order
                        ->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $status, $message, false)
                        ->save();

                    $helper->log(
                        sprintf(
                            '%s: Payment status was changed to PENDING for order %s. Order status has been automatically changed to: %s',
                            $jobCode,
                            $order->getIncrementId(),
                            $status
                        ),
                        Zend_Log::INFO
                    );
                }

            } catch (Mage_Core_Exception $e) {
                Mage::logException($e);
                $helper->log(
                    sprintf(
                        '%s: Error on checking payment method information for order %s: %s',
                        $jobCode,
                        $order->getIncrementId(),
                        $e->getMessage()
                    ),
                    Zend_Log::ERR
                );
            }

            // save payment object in any case to update 'afterpay_fetched_at'
            $payment->save();
        }
    }

    /**
     * Cron job which looks for Pending Payment orders and does following:
     *  - try to look up afterpay_order_id by afterpay_token and then check status of payment
     *  - if there is no afterpay_token then cancel order in 1 hour
     *  - if it can't fetch afterpay_order_id then cancel order in 1 hour
     * Cancellation in 1 hour is needed in order not to keep too much inventory for non-paid orders.
     *
     * @param Mage_Cron_Model_Schedule $schedule
     */
    public function fetchPendingPaymentOrdersInfo(Mage_Cron_Model_Schedule $schedule)
    {
        $helper = $this->helper();
        $helper->log('CRON JOB - fetchPendingPaymentOrdersInfo ...', Zend_Log::DEBUG);

        /** @var Mage_Sales_Model_Resource_Order_Collection|Mage_Sales_Model_Order[] $collection */
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->join(
            array('p' => 'sales/order_payment'),
            'main_table.entity_id = p.parent_id',
            array()
        );
        $collection->addFieldToFilter('main_table.state', Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
            ->addFieldToFilter('p.method', array('like' => 'afterpay%'))
            ->addFieldToFilter('p.afterpay_order_id', array('null' => null));

        $collection->setOrder('main_table.updated_at', Varien_Data_Collection::SORT_ORDER_ASC);
        $collection->setPageSize(self::ORDERS_PROCESSING_LIMIT);
		

        if( empty($schedule) ) {
            //no schedule means this is being triggered from Admin
            $jobCode = "Admin";
        }
        else {
            $jobCode = $schedule->getJobCode();
        }
	

        if (!count($collection)) {
            $helper->log(sprintf('%s: There is no suitable orders to process', $jobCode), Zend_Log::DEBUG);
        }

        $pendingPaymentTimeout = (int)Mage::getStoreConfig('afterpay/cron/pending_payment_timeout');
            
        //store the cancellation warnings
        $cancellation_warnings = array();

        foreach ($collection as $order) {

            try {
                try {
                    $payment = $order->getPayment();
                    $token == $payment->getData('afterpay_token');

                    if ( !empty($token) ) { // try to fetch afterpay_order_id if order token is present

                        /** @var Afterpay_Afterpay_Model_Method_Base $paymentMethod */
                        $paymentMethod = $payment->getMethodInstance();
                        $paymentInfo   = $paymentMethod->fetchTransactionInfo($payment, null);
                        $paymentMethod->saveAfterpayOrderId($order, $paymentInfo['id']);

                        $helper->log(sprintf(
                            "Get payment information for pending payment order, " .
                            "magento_order_time=%s, current_time=%s, pending_payment_timeout=%s, " .
                            "magento_order_id=%s, afterpay_order_token=%s, payment_info=%s",

                            date('Y-m-d H:i:s', strtotime($order->getCreatedAt())),
                            date('Y-m-d H:i:s', time()),
                            $pendingPaymentTimeout,
                            $order->getIncrementId(),
                            $token,
                            $paymentInfo
                        ));

                        // create order transaction and save data to database
                        $paymentMethod->createOrderTransaction($order, !in_array($paymentInfo['status'], array($paymentMethod::RESPONSE_STATUS_FAILED, $paymentMethod::RESPONSE_STATUS_DECLINED)));
                        $payment->save();
                        $order->save();

                        $this->fetchOrderPaymentStatus($order, $jobCode);

                    }
                } catch (Afterpay_Afterpay_Exception $e) {
                    $helper->log(sprintf("%s: Cannot update payment status for order %s: %s", $jobCode, $order->getIncrementId(), $e->getMessage()), Zend_Log::WARN);
                }

                // if order wasn't paid because of any reason then cancel it in an hour
                if (($order->getState() === Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
                    && (time() - strtotime($order->getCreatedAt()) >= $pendingPaymentTimeout)) {

                    /** @var Mage_Sales_Model_Order_Status_History $history */
                    $historyMessage = $helper->__(
                        'Afterpay: Payment was not received. ' .
                        'Order has been received more than ' . $pendingPaymentTimeout .
                        ' seconds ago and the payment is considered failed.'
                    );
                    if ($order->canCancel()) {
                        $order->cancel();

                        $history = $order->addStatusHistoryComment($historyMessage . $helper->__(
                            ' Order has been cancelled automatically.'
                        ));
                        $history->save();
                        $order->save();

                        $helper->log(sprintf(
                            '%s: Order %s has been automatically cancelled. It has been placed %s (more than %s seconds ago) and still doesn\'t have afterpay_token.',
                            $jobCode, $order->getIncrementId(), $order->getCreatedAt(), $pendingPaymentTimeout), Zend_Log::INFO
                        );
                    } else {

                        $message = $historyMessage . $helper->__(
                                ' Order cannot be canceled automatically. Please cancel it manually.'
                            );

                        if (!$this->orderHasHistoryComment($order, $message)) {
                            $history = $order->addStatusHistoryComment($message);
                            $history->save();
                            $order->save();
                        }

                        $helper->log(
                            sprintf('Afterpay Error: Order %s cannot be cancelled. Please edit it manually.',
                                $order->getIncrementId()
                            )
                        );
                        
                        //If Admin Show Notifications
                        if( empty($schedule) ) {
                            $cancellation_warnings[] = $order->getIncrementId();
                        }
                    }
                }

            } catch (Mage_Core_Exception $e) {
                $helper->log(sprintf("%s: Error on trial to update payment status for order %s\n%s", $jobCode, $order->getIncrementId(), $e->getMessage()), Zend_Log::ERR);
            }
        }



        //If Admin Show Notifications
        if( empty($schedule) ) {
            if( count($cancellation_warnings) < 1 ) {
                Mage::getSingleton('core/session')->addSuccess('Transactions Status Processed');
            }
            else {
                Mage::getSingleton('core/session')->addWarning(
                    sprintf('Afterpay Warning: Order %s cannot be cancelled. Please edit it manually.',
                        implode(',', $cancellation_warnings)
                    )
                );
            }
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string $comment
     * @return boolean
     */
    private function orderHasHistoryComment(Mage_Sales_Model_Order $order, $comment)
    {
        /** @var $history Mage_Sales_Model_Order_Status_History */
        foreach ($order->getAllStatusHistory() as $history) {
            if ($history->getComment() === $comment) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Afterpay_Afterpay_Helper_Data
     */
    protected function helper()
    {
        return Mage::helper('afterpay');
    }

    /**
     * Get prepared Processing order collection to re-check payment information (needed for pre-approved orders)
     *
     * @return Mage_Sales_Model_Order[]|Mage_Sales_Model_Resource_Order_Collection
     */
    protected function getPreApprovedOrderCollection()
    {
        /** @var Mage_Sales_Model_Resource_Order_Collection|Mage_Sales_Model_Order[] $collection */
        $collection = Mage::getResourceModel('sales/order_collection');

        //join payment method table
        $collection->join(
            array('p' => 'sales/order_payment'),
            'main_table.entity_id = p.parent_id',
            array('method', 'afterpay_token', 'afterpay_order_id', 'afterpay_fetched_at')
        );

        // filter by processing stage & Afterpay payment method
        $collection
            ->addFieldToFilter('main_table.state', Mage_Sales_Model_Order::STATE_PROCESSING)
            ->addFieldToFilter('p.method', array('like' => 'afterpay%'));

        // filter orders without fetched payment method information more than 1 hour
        $expiration = (int)Mage::getStoreConfig('afterpay/cron/check_preapproved_orders_interval');
        $collection->addFieldToFilter(
            array('afterpay_fetched_at', 'afterpay_fetched_at'),
            array(
                array('null' => null),
                array(
                    'lt' => new Zend_Db_Expr(
                        "DATE_SUB( '" . now() . "', INTERVAL " . intval($expiration) . " SECOND )"
                    )
                )
            )
        );

        // filter orders that has update during last 24 hours
        $expiration = (int)Mage::getStoreConfig('afterpay/cron/check_preapproved_orders_timeout');
        $collection->addFieldToFilter(
            'main_table.updated_at',
            array(
                'gt' => new Zend_Db_Expr("DATE_SUB( '" . now() . "', INTERVAL " . intval($expiration) . " SECOND )")
            )
        );

        // set order by fetch info date and order update date
        $collection->setOrder('p.afterpay_fetched_at', Varien_Data_Collection::SORT_ORDER_ASC);
        $collection->setOrder('main_table.updated_at', Varien_Data_Collection::SORT_ORDER_ASC);

        // limit 50 results
        $collection->setPageSize(self::ORDERS_PROCESSING_LIMIT);

        return $collection;
    }

    /**
     * get installments prices for products-list
     *
     * @param Varien_Event_Observer $observer
     */
    public function categoryProductListLoad(Varien_Event_Observer $observer)
    {

        /** @var Afterpay_Afterpay_Helper_Installments $helper */
        $helper = Mage::helper('afterpay/installments');

        if ($helper->isProductListInstallmentsEnabled()) {

            /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
            $productCollection = $observer->getCollection();

            $productPricesArray = array();

            if ($helper->isDependFromOrderTotalLimit()) {
                $orderTotalLimit = $helper->getOrderTotalLimit();

                foreach ($productCollection as $product) {
                    $productPrice = $product->getFinalPrice();

                    if ($productPrice < $orderTotalLimit) {
                        $productPricesArray[$product->getId()] = $helper->getProductInstallmentPrice($productPrice);
                    }
                }
            } else {
                foreach ($productCollection as $product) {
                    $productPricesArray[$product->getId()] = $helper->getProductInstallmentPrice(
                        $product->getFinalPrice()
                    );
                }
            }

            $installmentsBlock = Mage::app()->getLayout()->getBlock('product.list.afterpay.installments');
            if ($installmentsBlock) {
                $installmentsBlock->setData(
                    'products_installment_prices',
                    $productPricesArray
                );
            }

        }

    }

    /**
     * The function is called after Order Shipment saved
     *
     * @param Varien_Event_Observer $observer
     */
    public function salesOrderShipmentSaveAfter(Varien_Event_Observer $observer)
    {
        // adding track number and courier to SetShipped Api queue
        try {
            /** @var Mage_Sales_Model_Order_Shipment $track */
            $shipment = $observer->getShipment();

            if ($shipment instanceof Mage_Sales_Model_Order_Shipment) {
                $tracks         = $shipment->getAllTracks();
                $courier        = null;
                $trackingNumber = null;
                if (!empty($tracks)) {
                    $courier        = $tracks[0]->getTitle();
                    $trackingNumber = $tracks[0]->getNumber();
                }

                /** @var Mage_Sales_Model_Order $order */
                $order = $shipment->getOrder();

                if ($order instanceof Mage_Sales_Model_Order) {
                    /** @var Mage_Sales_Model_Order_Payment $payment */
                    $payment = $order->getPayment();

                    if ($payment instanceof Mage_Sales_Model_Order_Payment) {

                        if ($payment->getMethodInstance() instanceof Afterpay_Afterpay_Model_Method_Base) {

                            $shippedApiQueue = Mage::getModel('afterpay/shippedApiQueue');
                            $shippedApiQueue->setData(
                                array(
                                    'tracking_number' => $trackingNumber,
                                    'courier'         => $courier,
                                    'payment_id'      => $payment->getId()
                                )
                            );

                            $shippedApiQueue->save();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Mage::helper('afterpay')->log($e->getMessage());
            Mage::logException($e);
        }
    }

    /**
     * Cron job which sends shipped orders info to Afterpay API
     *
     * @param Mage_Cron_Model_Schedule $schedule
     *
     * @throws Exception
     */
    public function sendQueuedSetShippedApiRequests(Mage_Cron_Model_Schedule $schedule)
    {
        $jobCode = $schedule->getJobCode();
        $enabled = Mage::getStoreConfigFlag('afterpay/api/setshipped_api_enabled');

        if (!$enabled) {
            Mage::helper('afterpay')->log(sprintf('%s: SetShipped API Integration is disabled.', $jobCode), Zend_Log::DEBUG);
        } else {

            Mage::helper('afterpay')->log(sprintf('%s: START', $jobCode), Zend_Log::DEBUG);

            /** @var Afterpay_Afterpay_Model_Resource_ShippedApiQueue_Collection $collection */
            $collection = Mage::getResourceModel('afterpay/shippedApiQueue_collection')
                ->setOrder('main_table.shipped_api_queue_id', Varien_Data_Collection::SORT_ORDER_ASC)
                ->setOrder('main_table.errors_count', Varien_Data_Collection::SORT_ORDER_ASC)
                ->setPageSize(self::SET_SHIPPED_API_REQUESTS_LIMIT);

            $processedCount = 0;
            Mage::helper('afterpay')->log(sprintf('%s: Processing %s items from queue', $jobCode, count($collection)), Zend_Log::INFO);

            foreach ($collection as $queueItem) {

                $paymentId = $queueItem->getPaymentId();

                try {

                    /** @var Mage_Sales_Model_Order_Payment $payment */
                    $payment = Mage::getModel('Mage_Sales_Model_Order_Payment')->load($paymentId);
                    $order   = Mage::getModel('sales/order')->load($payment->getParentId());

                    /** @var Afterpay_Afterpay_Model_Method_Base $method */
                    $method = $payment->getMethodInstance();
                    $method->setStore($order->getStore());
                    $method->sendShippedApiRequest(
                        $payment->getAfterpayOrderId(),
                        $queueItem->getTrackingNumber(),
                        $queueItem->getCourier()
                    );

                    $queueItem->delete();

                    $processedCount++;

                } catch (Exception $e) {
                    Mage::logException($e);

                    $queueItem->setErrorsCount($queueItem->getErrorsCount() + 1);

                    if ($queueItem->getErrorsCount() < self::SET_SHIPPED_API_QUEUE_ERRORS_LIMIT) {
                        Mage::helper('afterpay')->log(
                            sprintf("Can't send SetShipped API request. Increasing errors counter.\n%s", print_r($queueItem->getData(), true), $e->getMessage()),
                            Zend_Log::WARN
                        );
                        $queueItem->save();
                    } else {
                        Mage::helper('afterpay')->log(
                            sprintf("Can't send SetShipped API request. Limit of errors count reached. Deleting task from queue.\n%s", print_r($queueItem->getData(), true), $e->getMessage()),
                            Zend_Log::ERR
                        );
                        $queueItem->delete();
                    }

                }

            }

            Mage::helper('afterpay')->log(sprintf('%s: Successfully sent %s tracking codes to Afterpay API', $jobCode, $processedCount), Zend_Log::INFO);
            Mage::helper('afterpay')->log(sprintf('%s: END', $jobCode), Zend_Log::DEBUG);
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string                 $logPrefix
     * @throws Exception
     */
    protected function fetchOrderPaymentStatus($order, $logPrefix)
    {
        $payment = $order->getPayment();
        $helper  = $this->helper();

        try {

            $payment->setData('afterpay_fetched_at', time());
            $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_UPDATE, true);

            // save changes to order only if there is a change to order status
            // this helps to avoid a lot of span on order history
            if (!$order->isPaymentReview()) {

                // send email after approval of payment if it wasn't sent before
                $paymentMethod = $payment->getMethodInstance();
                if ($order->getState() === Mage_Sales_Model_Order::STATE_PROCESSING) {
                    if (!$order->getEmailSent() &&
                        $paymentMethod->getConfigData('order_email', $order->getStoreId())
                    ) {
                        try {
                            $order->sendNewOrderEmail();
                            $order->setEmailSent(true);
                        } catch (Exception $e) {
                            Mage::logException($e);
                            $order->setEmailSent(false);
                        }
                    }
                }

                $order->save();

                if ($order->getState() === Mage_Sales_Model_Order::STATE_PROCESSING) {
                    $logPrefix = $logPrefix . ': ';

                    try {
                        $helper->createInvoice($order);
                        $helper->log($helper->__($logPrefix . 'Invoice successfully created'), Zend_Log::DEBUG);
                    } catch (Afterpay_Afterpay_Exception $e) {
                        /** @var Mage_Sales_Model_Order_Status_History $orderStatusHistory */
                        $orderStatusHistory = $order->addStatusHistoryComment(
                            $helper->__('%s. Invoice is not created', $e->getMessage())
                        );

                        $orderStatusHistory->save();

                        $helper->log($helper->__($logPrefix . '%s. Invoice is not created', $e->getMessage()), Zend_Log::INFO);
                    } catch (Exception $e) {
                        Mage::logException($e);
                        $helper->log($helper->__($logPrefix . 'Invoice creation failed with message: %s', $e->getMessage()), Zend_Log::ERR);
                    }
                }
            }

            $helper->log(
                sprintf(
                    '%s: Payment info was successfully fetched for order %s. Order status: %s',
                    $logPrefix,
                    $order->getIncrementId(),
                    $order->getStatus()
                ),
                Zend_Log::INFO
            );

        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);

            $helper->log(
                sprintf(
                    '%s: Error on fetching payment info for order %s: %s',
                    $logPrefix,
                    $order->getIncrementId(),
                    $e->getMessage()
                ),
                Zend_Log::ERR
            );
        }

        $payment->save(); // save payment object in any case to update 'afterpay_fetched_at'
    }

    /**
     * Get a database connection
     *
     * @return Varien_Db_Adapter_Interface
     */
    public function getConnection()
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    /**
     * Set a system configuration value
     *
     * This method is a copy of the Mage_Core_Model_Resource_Setup method, that can't really be called directly
     *
     * @param string    $path       Path to system config value
     * @param mixed     $value      Value to set
     * @param string    $scope      Scope level to delete
     * @param int       $scopeId    Scope ID to delete
     * @return $this
     */
    protected function _setConfigData($path, $value, $scope = 'default', $scopeId = 0)
    {
        $table = Mage::getSingleton('core/resource')->getTableName('core/config_data');
        // this is a fix for mysql 4.1
        $this->getConnection()->showTableStatus($table);

        $data  = array(
            'scope'     => $scope,
            'scope_id'  => $scopeId,
            'path'      => $path,
            'value'     => $value
        );
        $this->getConnection()->insertOnDuplicate($table, $data, array('value'));
        return $this;
    }

    /**
     * Delete a system configuration value
     *
     * This method is a copy of the Mage_Core_Model_Resource_Setup method, that can't really be called directly
     *
     * @param string $path  Path to system config value
     * @param string $scope Configuration scope
     * @return $this
     */
    protected function _deleteConfigData($path, $scope = null)
    {
        $where = array('path = ?' => $path);
        if (!is_null($scope)) {
            $where['scope = ?'] = $scope;
        }
        $this->getConnection()->delete(Mage::getSingleton('core/resource')->getTableName('core/config_data'), $where);
        return $this;
    }

    /**
     * Update the order limit values for the Afterpay payment methods
     *
     * Note that in order to fully set configuration values, we clear the config cache after this method runs
     *
     * @param mixed $observer This is unused and is here for compatibility with the Magento event system
     * @throws Mage_Core_Exception
     */
    public function updateOrderLimits($observer = null)
    {
        $configs = array(
            'PBI'                   => 'afterpaypayovertime',
            'PAY_BY_INSTALLMENT'    => 'afterpaypayovertime',
            // 'PAD'                   => 'afterpaybeforeyoupay',
        );

        $base = new Afterpay_Afterpay_Model_Method_Payovertime();

        foreach ($configs as $tla => $payment) {
            if (!Mage::getStoreConfigFlag('payment/' . $payment . '/active')) {
                continue;
            }

            $values = $base->getPaymentAmounts($payment, $tla);

            if( !$values ) {
                continue;
            }

            $path = 'payment/' . $payment . '/';

            if (isset($values['minimumAmount'])) {
                $this->_setConfigData($path . 'min_order_total', $values['minimumAmount']['amount']);
            } else {
                $this->_deleteConfigData($path . 'min_order_total');
            }

            if (isset($values['maximumAmount'])) {
                $this->_setConfigData($path . 'max_order_total', $values['maximumAmount']['amount']);
            } else {
                $this->_deleteConfigData($path . 'max_order_total');
            }
        }

        // after changing system configuration, we need to clear the config cache
        Mage::app()->cleanCache(array(Mage_Core_Model_Config::CACHE_TAG));
    }

    /**
     * Add a warning message to show that Magento logging is disabled
     *
     * @param mixed $observer
     */
    public function addLogWarningMessage($observer)
    {
        $params = Mage::app()->getRequest()->getParams();

        // if we are editing the afterpay section of the system configuration
        if (isset ($params['section']) && $params['section'] == 'afterpay') {
            if (Mage::getStoreConfigFlag('afterpay/general/debug') &&
               !Mage::getStoreConfigFlag('dev/log/active')) {
                // Afterpay debug process is enabled, but magento core logging is disabled
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('afterpay')->__('Afterpay logging is enabled however core Magento logging is disabled, so Afterpay debug logs will not be written. Please turn on Magento logging at Developer -> Log Settings.')
                );
            }

            if (Mage::helper('afterpay/checkout')->isUsingUnsupportedCheckout()) {
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('afterpay')->__('You appear to be using a checkout extension not fully supported by Afterpay. The Afterpay popup will be displayed over a new, empty, page.')
                );
            }
        }
    }

    /**
     * Set the afterpay order token as part of the order body response
     *
     * @param $observer
     * @return $this
     */
    public function addTokenToOrderResponse($observer)
    {
	$order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
        if ($order instanceof Mage_Sales_Model_Order) {
            $payment = $order->getPayment();
            if ($payment instanceof Mage_Payment_Model_Info && $payment->getMethodInstance() instanceof Afterpay_Afterpay_Model_Method_Base) {
                $response = Mage::app()->getResponse();
                $helper = Mage::helper('core');
                $responseBody = $helper->jsonDecode($response->getBody());
                $afterpayToken = $payment->getAfterpayToken();
                $responseBody['afterpayToken'] = $afterpayToken;
                $response->setBody($helper->jsonEncode($responseBody));

                $this->helper()->log('Setting afterpay token to order (' . $order->getIncrementId() . ') response : ' . $afterpayToken, Zend_Log::DEBUG);
            }
        }
        return $this;
    }

    public function adminhtmlWidgetContainerHtmlBefore($event)
    {
	
    	$container = $event->getBlock();
        
        if( !empty($container) && $container->getType() == 'adminhtml/sales_order') {

            $data = array(
                'label'     => 'Afterpay Transaction Update',
                'class'     => 'afterpay-transaction',
                'onclick'   => 'setLocation(\' '  . Mage::helper('adminhtml')->getUrl('adminhtml/afterpay/fetchPendingPaymentOrdersInfo') . '\')',
            );
            $container->addButton('my_button_identifier', $data);
        }
 
        return $this;

    }
    
    public function assignOrderStatus($observer)
    {
        /* @var Mage_Sales_Model_Order $order */
        $payment = $observer->getEvent()->getPayment();
        $order = $payment->getOrder();
        $status = Mage::getModel('afterpay/method_payovertime')->getPaymentReviewStatus();

        // Apply order status for specific order
        if ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW &&        // Order State is Payment Review
            $payment->getMethod() == Afterpay_Afterpay_Model_Method_Payovertime::CODE && // Payment using Mony Payments
            $order->getStatus() != $status                                               // Order status is not the same
        ) {
            // Set status to be selected payment review status from admin
            $order->setStatus($status);
        }
    }

    /**
     * Adding extra layout handle (i.e <default_handle>_MODULE_<module_name_case_sensitive>)
     *
     * Due to some other checkout extension will use the same handle but different methodologies.
     * Example: Idev_OneStepCheckout and MageStore_Onestepcheckout
     *
     * @param $observer
     */
    public function addModuleToHandle($observer)
    {
        // Get request
        $request = Mage::app()->getRequest();

        // Applied for only url onestepcheckout/index/index
        if ($request->getModuleName() == 'onestepcheckout' &&
            $request->getControllerName() == 'index' &&
            $request->getActionName() == 'index') {

            /* @var $update Mage_Core_Model_Layout_Update */
            $update = $observer->getEvent()->getLayout()->getUpdate();
            $update->addHandle('onestepcheckout_index_index_MODULE_' . $request->getControllerModule());
        }
    }
}
