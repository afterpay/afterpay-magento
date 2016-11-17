(function() {
    if (typeof window.Lightcheckout !== "undefined") {

        if (window.Afterpay.paymentAction == 'authorize_capture') {
            // Authorized Capture payment action

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
                        window.Afterpay.saveUrl, // use Afterpay controller
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
                }
            };
        } else {
            // Order

            /**
             * override save order for order payment action
             */
            window.Lightcheckout.prototype.saveorder = function() {
                this.showLoadinfo();

                var params = this.getFormData();

                var request = new Ajax.Request(this.save_order_url,
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

                            if (this.afterpay(response)) {
                                return;
                            }

                            if (typeof this.paynow === "function") {
                                if (this.paynow(response)) {
                                    return;
                                }
                            }

                            if (response.redirect) {
                                setLocation(response.redirect);
                            } else if (response.error) {
                                if (response.message) {
                                    alert(response.message);
                                }
                            } else if (response.update_section) {
                                this.hideLoadinfo();
                                this.accordion.currentSection = 'opc-review';
                                this.innerHTMLwithScripts($('checkout-update-section'), response.update_section.html);
                            }
                            this.hideLoadinfo();

                        }.bind(this),
                        onFailure: function () {

                        }
                    });
            };

            /**
             * New function to calculate afterpay plugins
             *
             * @param response
             * @returns {boolean}
             */
            window.Lightcheckout.prototype.afterpay = function(response) {
                if (payment.currentMethod == 'afterpaypayovertime') {
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
                        return true;
                    }
                }
            }
        }
    }

    /**
     * Override NextStep after place order for order payment action
     */
    if (typeof window.LightcheckoutReview !== "undefined" && window.Afterpay.paymentAction == 'order') {
        var original = window.LightcheckoutReview.prototype.nextStep;
        window.LightcheckoutReview.prototype.nextStep = function (transport) {
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
            original.apply(this, arguments);
        };
    }

})();