/**
 * Hotel Booking Flow & Checkout Script
 *
 * @package SVAGO
 */

(function($) {
    'use strict';

    function initBookingFlow() {
        var $guestForm = $('#svago-guest-form');
        var $policyCheckbox = $('#svago-policy-checkbox');
        var $payBtn = $('#svago-proceed-payment-btn');
        var $alertBox = $('#svago-availability-alert');
        var $alertMsg = $('#svago-availability-alert-msg');

        // Helper functions for field validation & highlighting
        function setFieldError($field, message, $wrapper) {
            var $group = $field.closest('.svago-step3-field-group');
            $group.addClass('has-error');
            if ($wrapper && $wrapper.length) {
                $wrapper.addClass('svago-input-error');
            } else {
                $field.addClass('svago-input-error');
            }
            $group.find('.svago-field-error').text(message).show();
        }

        function clearFieldError($field, $wrapper) {
            var $group = $field.closest('.svago-step3-field-group');
            $group.removeClass('has-error');
            if ($wrapper && $wrapper.length) {
                $wrapper.removeClass('svago-input-error');
            } else {
                $field.removeClass('svago-input-error');
            }
            $group.find('.svago-field-error').text('').hide();
        }

        // Real-time error removal as user types
        $(document).on('input change', '#svago-guest-first-name', function() {
            if ($.trim($(this).val()).length >= 2) {
                clearFieldError($(this));
            }
        });
        $(document).on('input change', '#svago-guest-last-name', function() {
            if ($.trim($(this).val()).length >= 1) {
                clearFieldError($(this));
            }
        });
        $(document).on('input change', '#svago-guest-email', function() {
            var val = $.trim($(this).val());
            if (val && /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val)) {
                clearFieldError($(this));
            }
        });
        $(document).on('input change', '#svago-guest-phone', function() {
            var val = $.trim($(this).val()).replace(/[\s\-()]/g, '');
            if (val && /^\d{7,15}$/.test(val)) {
                clearFieldError($(this), $(this).closest('.svago-step3-phone-wrapper'));
            }
        });

        // 1. Guest Form Validation and Direct Checkout Submission
        $(document).off('submit', '#svago-guest-form').on('submit', '#svago-guest-form', function(e) {
            e.preventDefault();

            var $form = $(this);
            var isValid = true;
            var $firstInvalid = null;

            // Clear previous errors
            $form.find('.svago-field-error').text('').hide();
            $form.find('.svago-input-error').removeClass('svago-input-error');
            $form.find('.svago-step3-field-group').removeClass('has-error');
            var $formAlert = $('#svago-guest-form-alert');
            $formAlert.hide().removeClass('svago-form-alert-error').text('');

            // Validate First Name
            var $firstName = $('#svago-guest-first-name');
            var firstNameVal = $.trim($firstName.val());
            if (!firstNameVal) {
                setFieldError($firstName, 'Please enter your first name.');
                isValid = false;
                $firstInvalid = $firstInvalid || $firstName;
            } else if (firstNameVal.length < 2) {
                setFieldError($firstName, 'First name must be at least 2 characters.');
                isValid = false;
                $firstInvalid = $firstInvalid || $firstName;
            }

            // Validate Last Name
            var $lastName = $('#svago-guest-last-name');
            var lastNameVal = $.trim($lastName.val());
            if (!lastNameVal) {
                setFieldError($lastName, 'Please enter your last name.');
                isValid = false;
                $firstInvalid = $firstInvalid || $lastName;
            }

            // Validate Email Address
            var $email = $('#svago-guest-email');
            var emailVal = $.trim($email.val());
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
            if (!emailVal) {
                setFieldError($email, 'Please enter your email address.');
                isValid = false;
                $firstInvalid = $firstInvalid || $email;
            } else if (!emailRegex.test(emailVal)) {
                setFieldError($email, 'Please enter a valid email address (e.g. name@example.com).');
                isValid = false;
                $firstInvalid = $firstInvalid || $email;
            }

            // Validate Mobile Phone Number
            var $phone = $('#svago-guest-phone');
            var rawPhone = $.trim($phone.val());
            var phoneDigits = rawPhone.replace(/[\s\-()]/g, '');
            var $phoneWrapper = $phone.closest('.svago-step3-phone-wrapper');
            if (!rawPhone) {
                setFieldError($phone, 'Please enter your contact mobile number.', $phoneWrapper);
                isValid = false;
                $firstInvalid = $firstInvalid || $phone;
            } else if (!/^\d{7,15}$/.test(phoneDigits)) {
                setFieldError($phone, 'Please enter a valid 7 to 15 digit mobile number.', $phoneWrapper);
                isValid = false;
                $firstInvalid = $firstInvalid || $phone;
            }

            // If invalid, display clear alert on the form and scroll to the first error
            if (!isValid) {
                $formAlert
                    .addClass('svago-form-alert-error')
                    .text('Please correct the highlighted fields below to proceed to checkout.')
                    .slideDown(250);

                if ($firstInvalid && $firstInvalid.length) {
                    $('html, body').animate({
                        scrollTop: $firstInvalid.offset().top - 120
                    }, 350);
                    $firstInvalid.focus();
                }
                return false;
            }

            // Submission & Checkout Redirect
            var $submitBtn = $form.find('.svago-step3-submit-btn, .svago-proceed-review');
            $submitBtn.addClass('svago-loading').prop('disabled', true);
            var processingText = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.i18n && svago_booking_vars.i18n.processing) ? svago_booking_vars.i18n.processing : 'Preparing Checkout...';
            $submitBtn.find('.svago-btn-text').text(processingText);

            var formData = {
                action: 'svago_save_guest_details',
                nonce: (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.nonce) ? svago_booking_vars.nonce : '',
                first_name: firstNameVal,
                last_name: lastNameVal,
                email: emailVal,
                phone_country: $('#svago-phone-country').val() || '+91',
                phone: phoneDigits,
                eta: $('#svago-guest-eta').val(),
                special_requests: $.trim($('#svago-special-requests').val()),
                consent: $('#svago-guest-consent').is(':checked') ? 1 : 0,
                // Fallback booking session metadata
                room_id: $('#svago-guest-room-id').val(),
                room_type: $('#svago-guest-room-type').val(),
                room_name: $('#svago-guest-room-name').val(),
                room_image: $('#svago-guest-room-image').val(),
                check_in: $('#svago-guest-check-in').val(),
                check_out: $('#svago-guest-check-out').val(),
                nights: $('#svago-guest-nights').val(),
                adults: $('#svago-guest-adults').val(),
                children: $('#svago-guest-children').val(),
                price_per_night: $('#svago-guest-price').val(),
                total_amount: $('#svago-guest-total').val()
            };

            var ajaxUrl = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.ajax_url) ? svago_booking_vars.ajax_url : '/wp-admin/admin-ajax.php';

            $.post(ajaxUrl, formData, function(response) {
                if (response.success && response.data) {
                    var checkoutUrl = response.data.checkout_url || response.data.redirect_url || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.checkout_url : '') || '/checkout/';
                    var redirectText = (typeof svago_booking_vars !== 'undefined' && svago_booking_vars.i18n && svago_booking_vars.i18n.redirecting) ? svago_booking_vars.i18n.redirecting : 'Redirecting to Checkout...';
                    $submitBtn.find('.svago-btn-text').text(redirectText);
                    window.location.href = checkoutUrl;
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Unable to proceed to checkout. Please check your details.';
                    $formAlert
                        .addClass('svago-form-alert-error')
                        .text(msg)
                        .slideDown(250);

                    // Highlight specific server validation errors if provided
                    if (response.data && response.data.errors) {
                        $.each(response.data.errors, function(fieldKey, fieldMsg) {
                            if (fieldKey === 'first_name') setFieldError($('#svago-guest-first-name'), fieldMsg);
                            if (fieldKey === 'last_name') setFieldError($('#svago-guest-last-name'), fieldMsg);
                            if (fieldKey === 'email') setFieldError($('#svago-guest-email'), fieldMsg);
                            if (fieldKey === 'phone') setFieldError($('#svago-guest-phone'), fieldMsg, $('#svago-guest-phone').closest('.svago-step3-phone-wrapper'));
                        });
                    }

                    $('html, body').animate({
                        scrollTop: $form.offset().top - 90
                    }, 300);

                    $submitBtn.removeClass('svago-loading').prop('disabled', false);
                    $submitBtn.find('.svago-btn-text').html('🔒 PROCEED TO CHECKOUT &rarr;');
                }
            }).fail(function() {
                $formAlert
                    .addClass('svago-form-alert-error')
                    .text('Connection error. Please try again.')
                    .slideDown(250);
                $submitBtn.removeClass('svago-loading').prop('disabled', false);
                $submitBtn.find('.svago-btn-text').html('🔒 PROCEED TO CHECKOUT &rarr;');
            });
        });

        // Trigger submit when clicking Proceed to Checkout button
        $(document).off('click', '#svago-proceed-checkout-btn').on('click', '#svago-proceed-checkout-btn', function(e) {
            $('#svago-guest-form').trigger('submit');
        });

        // 2. Mandatory Policy Acceptance Checkbox (Task 3.4)
        if ($policyCheckbox.length && $payBtn.length) {
            $policyCheckbox.on('change', function() {
                var isChecked = $(this).is(':checked');
                $payBtn.prop('disabled', !isChecked);
                if (isChecked) {
                    $payBtn.removeClass('svago-btn-disabled');
                } else {
                    $payBtn.addClass('svago-btn-disabled');
                }
            });
        }

        // 3. Pre-Payment Availability Re-Check (Task 3.5 & 3.6)
        if ($payBtn.length) {
            $payBtn.on('click', function(e) {
                e.preventDefault();

                if (!$policyCheckbox.is(':checked')) {
                    alert('Please agree to the cancellation policy and hotel terms.');
                    return false;
                }

                $payBtn.addClass('svago-loading').prop('disabled', true);
                $alertBox.hide();

                $.post(svago_booking_vars.ajax_url, {
                    action: 'svago_prepayment_check',
                    nonce: svago_booking_vars.nonce,
                    policy_agreed: 1
                }, function(response) {
                    if (response.success && response.data && response.data.available) {
                        // Availability confirmed! Create order (Task 4.1)
                        $payBtn.html('<span class="svago-spinner" aria-hidden="true"></span> ' + (svago_booking_vars.i18n.processing || 'Processing Order...'));

                        $.post(svago_booking_vars.ajax_url, {
                            action: 'svago_create_order',
                            nonce: svago_booking_vars.nonce,
                            policy_agreed: 1
                        }, function(orderRes) {
                            if (orderRes.success && orderRes.data) {
                                var orderData = orderRes.data;
                                var checkoutUrl = orderData.checkout_url || orderData.redirect_url || (typeof svago_booking_vars !== 'undefined' ? svago_booking_vars.checkout_url : '') || '/checkout/';
                                
                                $payBtn.html('<span class="svago-spinner" aria-hidden="true"></span> ' + (svago_booking_vars.i18n.redirecting || 'Redirecting to Checkout...'));
                                window.location.href = checkoutUrl;
                            } else {
                                var msg = (orderRes.data && orderRes.data.message) ? orderRes.data.message : 'Unable to create order. Please try again.';
                                $alertMsg.text(msg);
                                $alertBox.slideDown(300);
                                $payBtn.removeClass('svago-loading').prop('disabled', false);
                            }
                        }).fail(function() {
                            $alertMsg.text('Order creation failed. Please try again.');
                            $alertBox.slideDown(300);
                            $payBtn.removeClass('svago-loading').prop('disabled', false);
                        });
                    } else {
                        // Room is unavailable or failure: Task 3.5 requires friendly message (NO raw error)
                        var errorMsg = (response.data && response.data.message) 
                            ? response.data.message 
                            : 'This room is no longer available for your dates. Please try other dates or rooms.';

                        $alertMsg.text(errorMsg);
                        $alertBox.slideDown(300);

                        // Scroll smoothly to alert box
                        $('html, body').animate({
                            scrollTop: $alertBox.offset().top - 80
                        }, 400);

                        $payBtn.removeClass('svago-loading').prop('disabled', true);
                        $policyCheckbox.prop('checked', false);
                    }
                }).fail(function() {
                    $alertMsg.text('Availability verification is temporarily unavailable. Please call the hotel reservations to complete your booking.');
                    $alertBox.slideDown(300);
                    $payBtn.removeClass('svago-loading').prop('disabled', true);
                });
            });
        }

        // 4. Back / Edit Navigation
        $('.svago-back-to-rooms').on('click', function() {
            var currentUrl = window.location.href.split('?')[0];
            window.location.href = currentUrl;
        });

        $('.svago-back-to-details, .svago-edit-guest-btn').on('click', function() {
            var currentUrl = window.location.href.split('?')[0];
            window.location.href = currentUrl + '?step=details';
        });
    }

    $(document).ready(function() {
        initBookingFlow();
    });

})(jQuery);
