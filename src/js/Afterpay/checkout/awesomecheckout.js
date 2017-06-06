(function() {
    if (typeof window.Review !== "undefined" && window.Afterpay.paymentAction == 'authorize_capture') {
        /**
         * Override save on order review function
         */
        var reviewSave = window.Review.prototype.save;
        window.Review.prototype.save = function() {
            // check payment method
            if (payment.currentMethod == 'afterpaypayovertime') {
                if (checkout.loadWaiting != false) return;
                checkout.setLoadWaiting('review');
                /**
                 * Override on complete to do afterpay payment
                 *
                 * @param transport
                 */
                // Run ajax to process Afterpay payment using Protorype
                var request = new Ajax.Request(
                    window.Afterpay.saveUrl, // use Afterpay controller
                    {
                        method: 'post',
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
                /**
                 * Call original function
                 */
                reviewSave.apply(this, arguments);
            }
        };
    }
})();