/**
 * Function specifically for MW OneStepCheckout. The class especially for it
 */
jQuery( document ).ready( function() {
    var form = document.getElementById('amscheckout-onepage');
    var action = form.getAttribute('action');

    // save in variable for default .submit
    var original = form.submit;

    /**
     * Create new definition on .submit
     *
     * - Check if using Afterpay and use new flow, do Ajax and pop up or redirect
     */

    //hacks the form to prevent override by other plugins
    jQuery("#amscheckout-submit").on("click", function(e) {

        if (payment.currentMethod == 'afterpaypayovertime') {

            e.preventDefault();
            e.stopPropagation();

            // prepare params
            var params = form.serialize(true);

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
    });
});
