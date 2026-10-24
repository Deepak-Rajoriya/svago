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

        // 1. Guest Form Validation and Submission (Task 3.2)
        if ($guestForm.length) {
            $guestForm.on('submit', function(e) {
                e.preventDefault();

                var isValid = true;
                $('.svago-field-error').text('').hide();

                // Validate First Name
                var $firstName = $('#svago-guest-first-name');
                if (!$.trim($firstName.val())) {
                    $firstName.siblings('.svago-field-error').text('Please enter first name.').show();
                    isValid = false;
                }

                // Validate Last Name
                var $lastName = $('#svago-guest-last-name');
                if (!$.trim($lastName.val())) {
                    $lastName.siblings('.svago-field-error').text('Please enter last name.').show();
                    isValid = false;
                }

                // Validate Email
                var $email = $('#svago-guest-email');
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!$.trim($email.val()) || !emailRegex.test($.trim($email.val()))) {
                    $email.siblings('.svago-field-error').text('Please enter a valid email address.').show();
                    isValid = false;
                }

                // Validate Phone
                var $phone = $('#svago-guest-phone');
                if (!$.trim($phone.val()) || $.trim($phone.val()).length < 6) {
                    $phone.closest('.svago-form-group').find('.svago-field-error').text('Please enter a valid mobile number.').show();
                    isValid = false;
                }

                if (!isValid) {
                    return false;
                }

                var $submitBtn = $guestForm.find('.svago-proceed-review');
                $submitBtn.addClass('svago-loading').prop('disabled', true);

                var formData = {
                    action: 'svago_save_guest_details',
                    nonce: svago_booking_vars.nonce,
                    first_name: $.trim($firstName.val()),
                    last_name: $.trim($lastName.val()),
                    email: $.trim($email.val()),
                    phone_country: $('#svago-phone-country').val(),
                    phone: $.trim($phone.val()),
                    eta: $('#svago-guest-eta').val(),
                    special_requests: $.trim($('#svago-special-requests').val()),
                    consent: $('#svago-guest-consent').is(':checked') ? 1 : 0
                };

                $.post(svago_booking_vars.ajax_url, formData, function(response) {
                    if (response.success) {
                        // Reload or navigate to review step
                        var currentUrl = window.location.href.split('?')[0];
                        window.location.href = currentUrl + '?step=review';
                    } else {
                        var msg = (response.data && response.data.message) ? response.data.message : 'Unable to save details.';
                        alert(msg);
                    }
                }).fail(function() {
                    alert('Request failed. Please try again.');
                }).always(function() {
                    $submitBtn.removeClass('svago-loading').prop('disabled', false);
                });
            });
        }

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

                                // If Razorpay SDK is present and key is set, launch modal (Task 4.2 & 4.3)
                                if (window.Razorpay && orderData.razorpay_key) {
                                    var rzpOptions = {
                                        key: orderData.razorpay_key,
                                        amount: orderData.amount_in_paise,
                                        currency: orderData.currency || 'INR',
                                        name: orderData.hotel_name || 'Hotel Reservation',
                                        description: 'Room Reservation #' + orderData.order_id,
                                        prefill: {
                                            name: orderData.guest_name,
                                            email: orderData.guest_email,
                                            contact: orderData.guest_phone
                                        },
                                        handler: function(paymentResponse) {
                                            $payBtn.html('<span class="svago-spinner" aria-hidden="true"></span> Verifying Payment...');
                                            $.post(svago_booking_vars.ajax_url, {
                                                action: 'svago_verify_payment',
                                                nonce: svago_booking_vars.nonce,
                                                order_id: orderData.order_id,
                                                razorpay_payment_id: paymentResponse.razorpay_payment_id,
                                                razorpay_order_id: paymentResponse.razorpay_order_id || '',
                                                razorpay_signature: paymentResponse.razorpay_signature || ''
                                            }, function(verifyRes) {
                                                if (verifyRes.success && verifyRes.data && verifyRes.data.redirect_url) {
                                                    window.location.href = verifyRes.data.redirect_url;
                                                } else if (orderData.confirmation_url) {
                                                    window.location.href = orderData.confirmation_url;
                                                }
                                            });
                                        },
                                        modal: {
                                            ondismiss: function() {
                                                $payBtn.removeClass('svago-loading').prop('disabled', false).html('🔒 Proceed to Pay');
                                            }
                                        }
                                    };
                                    var rzp = new window.Razorpay(rzpOptions);
                                    rzp.open();
                                } else {
                                    // Simulation / Direct capture when Razorpay SDK not loaded (e.g. test mode or standard fallback)
                                    $.post(svago_booking_vars.ajax_url, {
                                        action: 'svago_verify_payment',
                                        nonce: svago_booking_vars.nonce,
                                        order_id: orderData.order_id,
                                        razorpay_payment_id: 'pay_test_' + Date.now()
                                    }, function(verifyRes) {
                                        if (verifyRes.success && verifyRes.data && verifyRes.data.redirect_url) {
                                            window.location.href = verifyRes.data.redirect_url;
                                        } else if (orderData.confirmation_url) {
                                            window.location.href = orderData.confirmation_url;
                                        }
                                    });
                                }
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
