(function() {
    if (typeof window.Review !== "undefined") {
        var target = window.Review;
    }
    else if (typeof window.Payment !== "undefined") {
        var target = window.Payment;
    }
    else {
        var target = false;
    }

    if (target) {
        var reviewSave = target.prototype.save;
        target.prototype.save = function() {
            // check payment method
            if (payment.currentMethod == 'afterpaypayovertime') {
                this.saveUrl = window.AfterpayM1.saveUrl;
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

                /**
                 * Override the redirect if lightbox is selected
                 */
                if (window.AfterpayM1.redirectMode == 'lightbox') {
                    this.onSave = function(){};
                }
            }

            /**
             * Call original function
             */
            reviewSave.apply(this, arguments);
        };
    }
})();
