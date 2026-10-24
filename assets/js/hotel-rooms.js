/**
 * Hotel Booking Room Results Script
 *
 * @package SVAGO
 */

(function($) {
    'use strict';

    function initRoomCards() {
        var $resultsContainer = $('#svago-room-results');
        if (!$resultsContainer.length) {
            return;
        }

        // 1. Gallery Thumbnail Switcher
        $('.svago-thumb-btn').off('click').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var fullSrc = $btn.data('full-src');
            var $card = $btn.closest('.svago-room-card');

            $card.find('.svago-room-featured-img').attr('src', fullSrc);
            $btn.siblings().removeClass('active');
            $btn.addClass('active');
        });

        // 2. Rate Plan Selection
        $('.svago-rate-plan-item').off('click').on('click', function(e) {
            // If click was not on the Book Now button itself, just highlight the plan
            if (!$(e.target).hasClass('svago-book-btn')) {
                var $item = $(this);
                $item.siblings().removeClass('svago-rate-selected');
                $item.addClass('svago-rate-selected');
            }
        });

        // 3. Book Now / Select Rate Button (wires to Phase 3)
        $('.svago-book-btn').off('click').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var roomId = $btn.data('room-id');
            var rateId = $btn.data('rate-id');
            var totalAmount = $btn.data('total-amount');

            // Gather active search parameters
            var checkIn = $('#svago-check-in').val() || svago_booking_vars.current_check_in;
            var checkOut = $('#svago-check-out').val() || svago_booking_vars.current_check_out;
            var adults = $('#svago-adults').val() || svago_booking_vars.current_adults || 1;
            var children = $('#svago-children').val() || svago_booking_vars.current_children || 0;

            $btn.prop('disabled', true).text(svago_booking_vars.i18n.processing || 'Processing...');

            // Trigger custom event for Phase 3 booking flow integration
            $(document).trigger('svago_room_selected', {
                room_id: roomId,
                rate_id: rateId,
                check_in: checkIn,
                check_out: checkOut,
                adults: adults,
                children: children,
                total_amount: totalAmount
            });

            // Store in sessionStorage as seamless client-side handoff
            try {
                sessionStorage.setItem('svago_selected_booking', JSON.stringify({
                    room_id: roomId,
                    rate_id: rateId,
                    check_in: checkIn,
                    check_out: checkOut,
                    adults: adults,
                    children: children,
                    total_amount: totalAmount,
                    timestamp: new Date().getTime()
                }));
            } catch (err) {
                console.warn('Session storage write failed', err);
            }

            // Post to server session (Task 3.1)
            $.post(svago_booking_vars.ajax_url, {
                action: 'svago_select_room',
                nonce: svago_booking_vars.nonce,
                room_id: roomId,
                rate_id: rateId,
                check_in: checkIn,
                check_out: checkOut,
                adults: adults,
                children: children,
                child_ages: $('select[name="child_ages[]"]:not(:disabled)').map(function() { return $(this).val(); }).get()
            }, function(response) {
                if (response.success) {
                    if (svago_booking_vars.booking_url) {
                        window.location.href = svago_booking_vars.booking_url + '?step=details';
                    } else {
                        var currentUrl = window.location.href.split('?')[0];
                        window.location.href = currentUrl + '?step=details';
                    }
                } else {
                    var errorMsg = (response.data && response.data.message) ? response.data.message : 'Unable to reserve room.';
                    alert(errorMsg);
                    $btn.prop('disabled', false).text(svago_booking_vars.i18n.book_now || 'Book Now');
                }
            }).fail(function() {
                // If AJAX fails, fall back to query param navigation
                var targetUrl = svago_booking_vars.booking_url || window.location.href.split('?')[0];
                window.location.href = targetUrl + '?step=details&room_id=' + encodeURIComponent(roomId) + '&rate_id=' + encodeURIComponent(rateId);
            });
        });
    }

    // Expose globally for re-initialization after AJAX refresh
    window.svagoInitRoomCards = initRoomCards;

    $(document).ready(function() {
        initRoomCards();
    });

})(jQuery);
