(function($) {

    $(document).on('ready', function() {
        $('.afterpay-what-is-modal-trigger').fancybox({
            afterShow: function() {
                $('#afterpay-what-is-modal').find('.close-afterpay-button').on('click', function(event) {
                    event.preventDefault();
                    $.fancybox.close();
                })
            }
        })
    });

})(jQuery);
