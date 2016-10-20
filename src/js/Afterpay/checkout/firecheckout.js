(function() {
    if (typeof window.checkout !== "undefined") {

        if (window.Afterpay.paymentAction == 'authorize_capture') {
            // Authorized Capture payment action

            /**
             * Override the save method when placing order
             *
             * @type {FireCheckout.save}
             */
            var save = window.FireCheckout.prototype.save;
            window.FireCheckout.prototype.save = function (urlSuffix, forceSave) {
                // if we have paid with the afterpay pay over time method
                if (payment.currentMethod == 'afterpaypayovertime') {
                    this.urls.save = window.Afterpay.saveUrl;

                    /**
                     * Override response if using Afterpay.
                     * Check with response and do redirect or popup
                     *
                     * @param transport
                     */
                    this.setResponse = function(transport) {
                        var response = {};
                        // Parse the response - lifted from original method
                        try {
                            response = eval('(' + transport.responseText + ')');
                        }
                        catch (e) {
                            response = {};
                        }

                        // if the order has been successfully placed
                        if (response.success) {
                            window.checkout.setLoadWaiting(false);

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

                // call the original function
                save.apply(this, arguments);
            }
        } else {
            // Order payment action

            /**
             * Override response after placing order
             *
             * @type {FireCheckout.setResponse|*|(function(this:*))}
             */
            var setResponse = window.checkout.setResponse;
            window.checkout.setResponse = (function (transport) {
                // if we have paid with the afterpay pay over time method
                if (payment.currentMethod == 'afterpaypayovertime') {
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
                        window.checkout.setLoadWaiting(false);

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
                setResponse.apply(this, arguments);
            }).bind(window.checkout);
        }
    }
})();