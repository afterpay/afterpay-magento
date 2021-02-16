/**
 * Function specifically for Idev OneStepCheckout. The class especially for it
 */
(function() {
    var form = $('onestepcheckout-form');
    var action = form.getAttribute('action');

    // save in variable for default .submit
    var original = form.submit;

    /**
     * Create new definition on .submit
     *
     * - Check if using Afterpay and use new flow, do Ajax and pop up or redirect
     */
    form.submit = function() {
    //jQuery("#onestepcheckout-place-order").on("click", function() {
        if (payment.currentMethod == 'afterpaypayovertime') {
            // prepare params
            var params = form.serialize(true);

            var customer_password = jQuery('#billing\\:customer_password').val();

            if( typeof customer_password !== 'undefined' && customer_password.length ) {
                params.create_account = 1;
            }

            // Registration handling
            doAfterpayAPICall(window.AfterpayM1.saveUrl, params);
            return false;

        } else {
            original.apply(this, arguments);
        }
    //}); For the jQuery version
    };
})();


function doAfterpayAPICall( saveURL, params ) {
    // Ajax to start order token
    var request = new Ajax.Request(
        saveURL, // use Afterpay controller
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
