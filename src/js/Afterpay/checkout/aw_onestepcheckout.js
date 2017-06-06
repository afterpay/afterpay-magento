(function() {
    if (typeof window.AWOnestepcheckoutForm !== 'undefined') {

        if (window.Afterpay.paymentAction == 'authorize_capture') {
            // Authorized Capture

            /**
             * Changing the function before placing the order
             *
             * @type {AWOnestepcheckoutForm._sendPlaceOrderRequest}
             */
            var sendPlaceOrder = window.AWOnestepcheckoutForm.prototype._sendPlaceOrderRequest;
            window.AWOnestepcheckoutForm.prototype._sendPlaceOrderRequest = function () {
                // check if using afterpay as a payment method
                if (awOSCPayment.currentMethod == 'afterpaypayovertime' || payment.currentMethod == 'afterpaypayovertime') {
                    // Set the place order URL based on the configuration
                    this.placeOrderUrl = window.Afterpay.saveUrl;

                    /**
                     * onComplete function will run after Ajax is finished.
                     * 1. It will check if ajax return success and continue Afterpay
                     * 2. Redirect or alert when it's failed
                     *
                     * @param transport
                     */
                    this.onComplete = function(transport) {
                        var response = {};

                        // Parse the response - lifted from original method
                        try {
                            response = eval('(' + transport.responseText + ')');
                        } catch (e) {
                            response = {};
                        }

                        // If response success
                        if (response.success) {

                            //modified to suit API V1
                            if( window.afterpayReturnUrl === false ) {
                                AfterPay.init(); 
                            }
                            else {
                                AfterPay.init({
                                    relativeCallbackURL: window.afterpayReturnUrl
                                });
                            }

                            switch (window.Afterpay.redirectMode) {
                                case 'lightbox':
                                    AfterPay.display({
                                        token: response.token
                                    });
                                    break;

                                case 'redirect':
                                    AfterPay.redirect({
                                        token: response.token
                                    });
                                    break;
                            }
                        } else {
                            if (response.redirect) {
                                this.isSuccess = false;
                                location.href = response.redirect;
                            } else {
                                alert(response.message);
                            }
                        }
                    };
                }

                /**
                 * Call original function
                 */
                sendPlaceOrder.apply(this, arguments);
            };
        } else {
            // Order

            /**
             * Changing after ajax place order function
             *
             * @type {AWOnestepcheckoutForm.onComplete|*}
             */
            var onComplete = window.AWOnestepcheckoutForm.prototype.onComplete;
            window.AWOnestepcheckoutForm.prototype.onComplete = function (transport) {
                // if we have paid with the afterpay pay over time method
                if (awOSCPayment.currentMethod == 'afterpaypayovertime' || payment.currentMethod == 'afterpaypayovertime') {
                    var response = {};

                    // Parse the response - lifted from original method
                    try {
                        response = eval('(' + transport.responseText + ')');
                    }
                    catch (e) {
                        response = {};
                    }

                    // if the order has been successfully placed
                    if (response.success || response.redirect) {
                        AfterPay.init({
                            relativeCallbackURL: window.afterpayReturnUrl
                        });

                        switch (window.Afterpay.redirectMode) {
                            case 'lightbox':
                                AfterPay.display({
                                    token: response.afterpayToken
                                });

                                break;

                            case 'redirect':
                                AfterPay.redirect({
                                    token: response.afterpayToken
                                });

                                break;
                        }

                        // don't want to get taken to the success page just yet
                        return;
                    }
                }

                // call the original function
                onComplete.apply(this, arguments);
            };
        }
    }
})();