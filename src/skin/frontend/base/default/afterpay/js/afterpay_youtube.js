/**
 *
 * library to allow open youtube-video with high-quality and and auto-start
 *
 * @package Afterpay_Afterpay
 * @author VEN Development Team <info@ven.com>
 * @requires jQuery, fancybox
 */
;
(function ($) {

    // TODO: move variables and function, that are defined below, into $.fn.afterpayYoutube function
    "use strict";
    var player;

    //Load player api asynchronously.
    var tag = document.createElement('script');
    tag.src = "//www.youtube.com/iframe_api";
    var firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

    var setBestQuality = function (event) {
        var availableQualityLevels = player.getAvailableQualityLevels();

        if (availableQualityLevels.length) {
            player.setPlaybackQuality(availableQualityLevels[0]);
        }
    };

    // Fires whenever a player has finished loading
    function onPlayerReady(event) {
        setBestQuality(event);
        //Hacked to disable autostart on iPad
        if (/(iPhone|iPod|iPad|BlackBerry|Android)/g.test(navigator.userAgent) === false) {
            player.playVideo();
        }
    }

    // Fires when the player's state changes.
    function onPlayerStateChange(event) {
        // Go to the next video after the current one is finished playing
        if (event.data == YT.PlayerState.BUFFERING) {
            setBestQuality(event);
        }
        if (event.data == YT.PlayerState.PLAYING) {
            setBestQuality(event);
        }
        if (event.data === 0) {
            $.fancybox.close();
        }
    }

    $.fn.afterpayYoutube = function () {

        this.on('click', function (e) {
            e.preventDefault();
            // TODO: define it before current event
            var $this = $(this),
                url = '//www.youtube.com/embed/' + $this.data('id') + '?enablejsapi=1';

            $.fancybox({
                'padding': 25,
                'width': 680,
                'height': 400,
                'href': url,
                'type': 'iframe',
                'overlayOpacity': 0.8,
                'overlayColor': '#fff',
                afterLoad: function () {

                    // TODO: check that $('.fancybox-iframe').attr('id') exists to create player object
                    // Find the iframe ID
                    var id = $('.fancybox-iframe').attr('id');

                    // Create video player object and add event listeners
                    player = new YT.Player(id, {
                        events: {
                            'onReady': onPlayerReady,
                            'onStateChange': onPlayerStateChange
                        }
                    });
                }
            });
        });
    };
})(jQuery);
