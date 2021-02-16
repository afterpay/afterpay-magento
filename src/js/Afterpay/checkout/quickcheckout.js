/**
 * Override the FME Quick checkout JS function
 */
(function() {
    if (typeof window.Review !== 'undefined') {
        var original = window.Review.prototype.save;

        /**
         * Adding calculation for Afterpay payment method
         */
        window.Review.prototype.save = function () {
            // if we have paid with the afterpay pay over time method and use new flow
            if (payment.currentMethod == 'afterpaypayovertime') {
                // As per sage payment on checkout it self
                if ($("billing:use_for_shipping_yes").checked != true) {
                    shipping.setSameAsBilling(true);
                }
                // Set the Ajax Url to use afterpay url
                review_url = AfterpayM1.saveUrl;

                /**
                 * Override the response after ajax for afterpay method
                 *
                 * @param transport
                 */
                onestepcheckout.processRespone = function(transport) {
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
                            return;
                        } else {
                            alert(response.message);
                        }
                    }
                };
            }

            // call the original function
            original.apply(this, arguments);
        };
    }
})();
