(function() {
    if (typeof window.Review !== "undefined") {

        // Authorized capture
        if (window.Afterpay.paymentAction == 'authorize_capture') {
            /**
             * Function to new function authorized_capture
             */
            var reviewSave = window.Review.prototype.save;
            window.Review.prototype.save = function() {
                // check payment method
                if (payment.currentMethod == 'afterpaypayovertime') {
                    this.saveUrl = window.Afterpay.saveUrl;
                    /**
                     * Override on complete to do afterpay payment
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

                    /**
                     * Override the redirect if lightbox is selected
                     */
                    if (window.Afterpay.redirectMode == 'lightbox') {
                        this.onSave = function(){};
                    }
                }

                /**
                 * Call original function
                 */
                reviewSave.apply(this, arguments);
            };

        }
        // Order
        else {
            /**
             * Function to next steps
             *
             * @type {Review.nextStep}
             */
            var reviewNextStep = window.Review.prototype.nextStep;
            window.Review.prototype.nextStep = function (transport) {
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
                reviewNextStep.apply(this, arguments);
            };

        }
    }
})();