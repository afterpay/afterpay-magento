(function() {
    if (typeof window.IWD !== 'undefined' && typeof window.IWD.OPC !== 'undefined') {
        var original = window.IWD.OPC.prepareOrderResponse;

        window.IWD.OPC.prepareOrderResponse = function (response) {
            // if we have paid with the afterpay pay over time method
            if (payment.currentMethod == 'afterpaypayovertime') {
                // if the order has been successfully placed
                if (response.success || response.redirect) {
                    IWD.OPC.Checkout.hideLoader();
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