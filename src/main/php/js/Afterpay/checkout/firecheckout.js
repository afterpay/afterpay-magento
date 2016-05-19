(function() {
    if (typeof window.checkout !== "undefined") {
        var original = window.checkout.setResponse;

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
            original.apply(this, arguments);
        }).bind(window.checkout);
    }
})();