/**
 * Override the IWD checkout JS function
 */
(function() {
    if (typeof window.IWD !== 'undefined' && typeof window.IWD.OPC !== 'undefined') {

        if (window.Afterpay.paymentAction == 'authorize_capture') {
            // Authorized Capture Payment Action

            /**
             * Override Saving order checkout to run Afterpay method first
             *
             * @type {IWD.OPC.callSaveOrder|*}
             */
            var saveOrder = window.IWD.OPC.callSaveOrder;
            window.IWD.OPC.callSaveOrder = function (form) {
                // if we have paid with the afterpay pay over time method
                if (payment.currentMethod == 'afterpaypayovertime') {

                    // perform dispatch as per original
                    IWD.OPC.Plugin.dispatch('saveOrder');

                    // Run ajax to process Afterpay payment using Protorype
                    var request = new Ajax.Request(
                        window.Afterpay.saveUrl, // use Afterpay controller
                        {
                            method: 'post',
                            parameters: form,
                            onSuccess: function (transport) {
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

                            }.bind(this),
                            onFailure: function () {
                                alert('Afterpay Gateway is not available.');
                            }
                        }
                    );
                } else {
                    // call the original function
                    saveOrder.apply(this, arguments);
                }
            };

        } else {
            // Order Payment Action

            /**
             * Override handling response after order created for order payment action
             *
             * @type {IWD.OPC.prepareOrderResponse|*}
             */
            var prepareResponse = window.IWD.OPC.prepareOrderResponse;
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
                prepareResponse.apply(this, arguments);
            };

        }
    }
})();