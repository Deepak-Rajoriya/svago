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
            if (!$(e.target).hasClass('svago-book-btn') && !$(e.target).hasClass('svago-plan-book-btn')) {
                var $item = $(this);
                $item.siblings().removeClass('svago-rate-selected svago-plan-active');
                $item.addClass('svago-rate-selected svago-plan-active');
            }
        });

        // 3. Room Type Selection (Balcony vs Jacuzzi)
        var selectedRoomType = 'Balcony';
        var activeRoomId = '';

        $(document).on('click', '.svago-type-pill-btn', function(e) {
            e.preventDefault();
            $('.svago-type-pill-btn').removeClass('svago-type-active');
            $(this).addClass('svago-type-active');
            selectedRoomType = $(this).data('room-type') || 'Balcony';
        });

        // 4. Inner Step 1 -> Inner Step 2 Transition ("VIEW RATES & OFFERS")
        $('.svago-btn-view-rates').off('click').on('click', function(e) {
            e.preventDefault();
            var $card = $(this).closest('.svago-room-card-v2');
            var roomId = $(this).data('room-id') || $card.data('room-id');
            var roomTitle = $card.data('room-display') || $card.find('.svago-room-main-title').text();
            var roomTagline = $card.data('room-tagline') || $card.find('.svago-room-tagline').text();
            var defaultPlaceholder = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.plugin_url) 
                ? svago_booking_vars.plugin_url + 'assets/images/placeholder.png' 
                : '/wp-content/plugins/svago/assets/images/placeholder.png';
            var roomImg = $card.data('room-img') || $card.find('.svago-room-v2-img').attr('src') || defaultPlaceholder;

            activeRoomId = roomId;

            var $inner1 = $('#svago-step2-inner-1');
            var $inner2 = $('#svago-step2-inner-2');
            var $globalBack = $('#svago-step2-global-back');

            $('#svago-step2-recap-title').text(roomTitle);
            $('#svago-step2-recap-tagline').text(roomTagline);
            $('#svago-step2-recap-thumb').attr('src', roomImg).attr('alt', roomTitle).show();

            // Update Inner Step 2 Room Type cards with room details & image
            $('.svago-room-type-card').attr('data-room-id', roomId);
            $('.svago-btn-select-room-type').attr('data-room-id', roomId);
            if (roomTitle) {
                $('#svago-balcony-parent-name').text(roomTitle + ' • Balcony Type');
                $('#svago-jacuzzi-parent-name').text(roomTitle + ' • Jacuzzi Type');
            }
            if (roomImg) {
                $('.svago-type-card-img').attr('src', roomImg);
            }

            // Auto-select Room Type pill based on room theme
            if (roomTitle && roomTitle.toUpperCase().indexOf('JACUZZI') !== -1) {
                selectedRoomType = 'Jacuzzi';
                $('.svago-type-pill-btn').removeClass('svago-type-active');
                $('.svago-type-pill-btn[data-room-type="Jacuzzi"]').addClass('svago-type-active');
            } else {
                selectedRoomType = 'Balcony';
                $('.svago-type-pill-btn').removeClass('svago-type-active');
                $('.svago-type-pill-btn[data-room-type="Balcony"]').addClass('svago-type-active');
            }

            $('.svago-room-rates-group').hide();
            var $targetGroup = $('.svago-room-rates-group[data-room-id="' + roomId + '"]');
            if ($targetGroup.length) {
                $targetGroup.show();
            }

            $inner1.hide();
            $inner2.fadeIn(200);

            var initialBackHref = $globalBack.data('initial-href') || '?step=calendar';
            $globalBack.attr('href', '#change-room').off('click.step2inner').on('click.step2inner', function(evt) {
                evt.preventDefault();
                $inner2.hide();
                $inner1.fadeIn(200);
                $globalBack.attr('href', initialBackHref).off('click.step2inner');
                $('html, body').animate({ scrollTop: $('#svago-room-results').offset().top - 20 }, 300);
            });

            $('html, body').animate({ scrollTop: $('#svago-room-results').offset().top - 20 }, 300);
        });

        // 4b. Selection of Room Type card CTA button (Proceed directly to Step 3: Guest Info)
        $(document).off('click.svagotype', '.svago-btn-select-room-type').on('click.svagotype', '.svago-btn-select-room-type', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var roomType = $btn.data('room-type') || selectedRoomType || 'Balcony';
            var roomId = $btn.attr('data-room-id') || $btn.data('room-id') || activeRoomId || $('#svago-step2-selected-room-id').val() || $('.svago-room-card-v2:first').data('room-id') || 'RM001';

            var checkIn = $('#svago-step2-check-in').val() || $('#svago-check-in').val() || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.current_check_in : '');
            var checkOut = $('#svago-step2-check-out').val() || $('#svago-check-out').val() || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.current_check_out : '');
            var adults = $('#svago-step2-adults').val() || $('#svago-adults').val() || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.current_adults : 2);
            var children = $('#svago-step2-children').val() || $('#svago-children').val() || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.current_children : 0);

            if (!checkIn) {
                checkIn = new Date().toISOString().slice(0, 10);
            }
            if (!checkOut || checkOut <= checkIn) {
                var d = new Date(checkIn);
                d.setDate(d.getDate() + 1);
                checkOut = d.toISOString().slice(0, 10);
            }

            var originalText = $btn.text();
            $btn.prop('disabled', true).text((typeof svago_booking_vars !== 'undefined' && svago_booking_vars.i18n && svago_booking_vars.i18n.processing) ? svago_booking_vars.i18n.processing : 'Processing...');

            // Store in client session storage
            try {
                sessionStorage.setItem('svago_selected_booking', JSON.stringify({
                    room_id: roomId,
                    room_type: roomType,
                    check_in: checkIn,
                    check_out: checkOut,
                    adults: adults,
                    children: children,
                    timestamp: new Date().getTime()
                }));
            } catch (err) {
                console.warn('Session storage write failed', err);
            }

            var targetBaseUrl = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.booking_url) ? svago_booking_vars.booking_url : window.location.href.split('?')[0];
            var destUrl = targetBaseUrl + '?step=details&room_id=' + encodeURIComponent(roomId) + 
                          '&room_type=' + encodeURIComponent(roomType) + 
                          '&check_in=' + encodeURIComponent(checkIn) + 
                          '&check_out=' + encodeURIComponent(checkOut) + 
                          '&adults=' + encodeURIComponent(adults) + 
                          '&children=' + encodeURIComponent(children);

            var navigated = false;
            function navigateNext() {
                if (!navigated) {
                    navigated = true;
                    window.location.href = destUrl;
                }
            }

            var fallbackTimer = setTimeout(navigateNext, 1200);

            // Post to server session and route directly to Step 3 (Guest Info)
            var ajaxUrl = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.ajax_url) ? svago_booking_vars.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.nonce) ? svago_booking_vars.nonce : '';

            $.post(ajaxUrl, {
                action: 'svago_select_room',
                nonce: nonce,
                room_id: roomId,
                room_type: roomType,
                check_in: checkIn,
                check_out: checkOut,
                adults: adults,
                children: children
            }, function(response) {
                clearTimeout(fallbackTimer);
                if (response && response.data && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    navigateNext();
                }
            }).fail(function() {
                clearTimeout(fallbackTimer);
                navigateNext();
            });
        });

        // 5. Change Room Button (Inner Step 2 -> Inner Step 1)
        $('#svago-btn-change-room').off('click').on('click', function(e) {
            e.preventDefault();
            var $inner1 = $('#svago-step2-inner-1');
            var $inner2 = $('#svago-step2-inner-2');
            var $globalBack = $('#svago-step2-global-back');
            var initialBackHref = $globalBack.data('initial-href') || '?step=calendar';

            $inner2.hide();
            $inner1.fadeIn(200);
            $globalBack.attr('href', initialBackHref).off('click.step2inner');
            $('html, body').animate({ scrollTop: $('#svago-room-results').offset().top - 20 }, 300);
        });

        // 6. Book Now / Select Rate Button (wires to Phase 3)
        $('.svago-book-btn').off('click').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            if ($btn.hasClass('svago-btn-select-room-type')) {
                return;
            }
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
                room_type: selectedRoomType,
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
                    room_type: selectedRoomType,
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
                room_type: selectedRoomType,
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
