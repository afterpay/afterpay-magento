/**
 * Override the IWD checkout JS function
 */
(function() {
    if (typeof window.IWD !== 'undefined' && typeof window.IWD.OPC !== 'undefined') {
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
                    window.AfterpayM1.saveUrl, // use Afterpay controller
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
    }
})();
