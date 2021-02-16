(function() {
    if (typeof window.AWOnestepcheckoutForm !== 'undefined') {
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
                this.placeOrderUrl = window.AfterpayM1.saveUrl;

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
                            if (typeof AfterPay.initialize === "function") {
                                AfterPay.initialize(window.afterpayCountryCode);
                            } else {
                                AfterPay.init();
                            }
                        }
                        else {
                            AfterPay.init({
                                relativeCallbackURL: window.afterpayReturnUrl
                            });
                        }

                        switch (window.AfterpayM1.redirectMode) {
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
    }
})();
