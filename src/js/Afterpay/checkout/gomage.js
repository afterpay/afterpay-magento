(function() {
    if (typeof window.Lightcheckout !== "undefined") {
        /**
         * Override saveorder ajax to get order token
         *
         * @type {Lightcheckout.prototype.saveorder|*|Lightcheckout.saveorder}
         */
        window.Lightcheckout.prototype.saveorder = function() {
            // Check wether is currently using afterpay
            if (payment.currentMethod == 'afterpaypayovertime') {
                this.showLoadinfo();
                // prepare params
                var params = this.getFormData();
                // Ajax to start order token
                var request = new Ajax.Request(
                    window.AfterpayM1.saveUrl, // use Afterpay controller
                    {
                        method: 'post',
                        parameters: params,
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
            }
        };
    }

})();
