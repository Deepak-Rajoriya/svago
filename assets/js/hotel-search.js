/**
 * Hotel Booking Search Widget Script
 *
 * @package SVAGO
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $searchContainer = $('#svago-search-widget');
        if (!$searchContainer.length) {
            return;
        }

        var $form = $searchContainer.find('.svago-search-form');
        var $checkIn = $('#svago-check-in');
        var $checkOut = $('#svago-check-out');

        // 1. Date synchronization: Check-out must be after check-in
        $checkIn.on('change', function() {
            var checkInVal = $(this).val();
            if (!checkInVal) return;

            var inDate = new Date(checkInVal);
            var nextDay = new Date(inDate);
            nextDay.setDate(nextDay.getDate() + 1);

            var nextDayStr = nextDay.toISOString().split('T')[0];
            $checkOut.attr('min', nextDayStr);

            var currentOutVal = $checkOut.val();
            if (!currentOutVal || currentOutVal <= checkInVal) {
                $checkOut.val(nextDayStr);
            }
        });

        // 2. Handle AJAX search submission if room results container exists on page
        $form.on('submit', function(e) {
            var $resultsContainer = $('#svago-room-results');
            if (!$resultsContainer.length) {
                // No results container on this page, let normal GET form submission proceed
                return true;
            }

            e.preventDefault();

            var $btn = $form.find('.svago-search-submit');
            $btn.addClass('svago-loading').prop('disabled', true);
            $resultsContainer.css('opacity', '0.5');

            var formData = {
                action: 'svago_get_availability',
                nonce: svago_booking_vars.nonce,
                check_in: $checkIn.val(),
                check_out: $checkOut.val(),
                adults: $('#svago-adults').val()
            };

            $.post(svago_booking_vars.ajax_url, formData, function(response) {
                if (response.success && response.data && response.data.html) {
                    $resultsContainer.replaceWith(response.data.html);
                    // Re-bind room results events
                    if (window.svagoInitRoomCards) {
                        window.svagoInitRoomCards();
                    }
                } else {
                    var errorMsg = (response.data && response.data.message) ? response.data.message : 'No rooms available for selected dates.';
                    alert(errorMsg);
                }
            }).fail(function() {
                alert('Unable to load availability. Please try again.');
            }).always(function() {
                $btn.removeClass('svago-loading').prop('disabled', false);
                $('#svago-room-results').css('opacity', '1');
            });
        });
    });

})(jQuery);
