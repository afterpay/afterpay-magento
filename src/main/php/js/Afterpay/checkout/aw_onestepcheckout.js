(function() {
    if (typeof window.AWOnestepcheckoutForm !== 'undefined') {
        var original = window.AWOnestepcheckoutForm.prototype.onComplete;

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
            original.apply(this, arguments);
        };
    }
})();