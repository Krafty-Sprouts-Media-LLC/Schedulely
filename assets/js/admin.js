/**
 * Schedulely Admin JavaScript
 * 
 * @package Schedulely
 */

(function ($) {
    'use strict';

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function () {
        initScheduleButton();
        initTimeValidation();
        initAuthorSelect();
        initTimePickers();
        initCapacityChecker();
        initAutoScheduleToggle();

        // Insight Panel Toggle
        $('.close-insight').on('click', function (e) {
            e.preventDefault();
            $(this).closest('.insight-panel').slideUp();
        });
    });

    /**
     * Handle manual schedule button with SweetAlert2
     */
    function initScheduleButton() {
        const button = document.getElementById('schedulely-schedule-now');
        if (!button) return;

        button.addEventListener('click', function (e) {
            e.preventDefault();

            // First, check capacity before proceeding
            checkCapacityBeforeScheduling(button);
        });
    }

    /**
     * Check capacity before scheduling and show warning if needed
     */
    function checkCapacityBeforeScheduling(button) {
        const startTime = $('#schedulely_start_time').val() || '5:00 PM';
        const endTime = $('#schedulely_end_time').val() || '11:00 PM';
        const minInterval = parseInt($('#schedulely_min_interval').val() || 40, 10);
        const postsPerDay = parseInt($('#schedulely_posts_per_day').val() || 8, 10);

        // Show loading
        Swal.fire({
            title: 'Checking Capacity...',
            html: 'Please wait...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch(schedulely_admin.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'schedulely_check_capacity',
                nonce: schedulely_admin.nonce,
                start_time: startTime,
                end_time: endTime,
                min_interval: minInterval,
                posts_per_day: postsPerDay
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const capacityData = data.data;

                    if (!capacityData.valid) {
                        Swal.fire({
                            title: 'Invalid Settings',
                            html: capacityData.error || 'Your time settings are invalid.',
                            icon: 'error',
                            confirmButtonColor: '#d63638'
                        });
                        return;
                    }

                    if (!capacityData.meets_quota) {
                        // Show warning with option to proceed
                        Swal.fire({
                            title: '⚠️ Capacity Warning',
                            html: `<div style="text-align: left;">
                            <p style="font-size: 15px; margin-bottom: 15px;">
                                <strong>Your settings can only fit ${capacityData.capacity} posts per day, but you want ${capacityData.desired_quota} posts.</strong>
                            </p>
                            <p style="font-size: 14px; margin-bottom: 10px;">
                                The plugin will schedule fewer posts than your quota. Do you want to proceed anyway?
                            </p>
                        </div>`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#2271b1',
                            cancelButtonColor: '#d63638',
                            confirmButtonText: 'Yes, Schedule Anyway',
                            cancelButtonText: 'Cancel',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                showScheduleConfirmation(button);
                            }
                        });
                    } else {
                        // Capacity is good, show normal confirmation
                        showScheduleConfirmation(button);
                    }
                } else {
                    // Error checking capacity, show normal confirmation
                    showScheduleConfirmation(button);
                }
            })
            .catch(error => {
                console.error('Capacity check error:', error);
                // On error, show normal confirmation
                showScheduleConfirmation(button);
            });
    }

    /**
     * Show normal schedule confirmation dialog
     */
    function showScheduleConfirmation(button) {
        Swal.fire({
            title: 'Schedule Posts Now?',
            html: 'This will schedule all available posts according to your settings.<br><br><strong>Do you want to continue?</strong>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2271b1',
            cancelButtonColor: '#d63638',
            confirmButtonText: 'Yes, Schedule Now',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                scheduleNow(button);
            }
        });
    }

    /**
     * Execute the scheduling process
     */
    function scheduleNow(button) {
        button.disabled = true;
        const originalText = button.textContent;
        button.innerHTML = '<span class="dashicons dashicons-update spin"></span> Scheduling...';

        Swal.fire({
            title: 'Scheduling Posts...',
            html: 'Please wait while we schedule your posts.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch(schedulely_admin.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'schedulely_manual_schedule',
                nonce: schedulely_admin.nonce
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        html: data.data.message || 'Posts scheduled successfully!',
                        icon: 'success',
                        confirmButtonColor: '#2271b1',
                        confirmButtonText: 'View Scheduled Posts',
                        showCancelButton: true,
                        cancelButtonText: 'Stay Here',
                        cancelButtonColor: '#50575e',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Navigate to WordPress scheduled posts page
                            window.location.href = schedulely_admin.scheduled_posts_url;
                        } else {
                            // Just reload to update the Upcoming Posts list
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        html: data.data.message || 'An error occurred while scheduling posts.',
                        icon: 'error',
                        confirmButtonColor: '#d63638',
                        confirmButtonText: 'Close'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    html: 'An unexpected error occurred. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#d63638',
                    confirmButtonText: 'Close'
                });
                console.error('Schedulely error:', error);
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = originalText;
            });
    }

    /**
     * Validate time window
     */
    function initTimeValidation() {
        const $startTime = $('#schedulely_start_time');
        const $endTime = $('#schedulely_end_time');

        if (!$startTime.length || !$endTime.length) {
            return;
        }

        function validateTimes() {
            const startVal = $startTime.val();
            const endVal = $endTime.val();

            if (!startVal || !endVal) {
                return;
            }

            // Convert to 24hr for comparison
            const startTime = convertTo24Hour(startVal);
            const endTime = convertTo24Hour(endVal);

            if (startTime >= endTime) {
                $endTime[0].setCustomValidity('End time must be after start time.');
            } else {
                $endTime[0].setCustomValidity('');
            }
        }

        $startTime.on('change', validateTimes);
        $endTime.on('change', validateTimes);
    }

    /**
     * Convert 12hr time to 24hr for comparison
     * 
     * @param {string} time12h Time in 12hr format (e.g., "5:00 PM")
     * @return {number} Minutes since midnight
     */
    function convertTo24Hour(time12h) {
        const [time, modifier] = time12h.split(' ');
        let [hours, minutes] = time.split(':');

        hours = parseInt(hours, 10);
        minutes = parseInt(minutes, 10);

        if (modifier === 'PM' && hours !== 12) {
            hours += 12;
        }
        if (modifier === 'AM' && hours === 12) {
            hours = 0;
        }

        return hours * 60 + minutes;
    }

    /**
     * Initialize Select2 for author exclusion
     */
    function initAuthorSelect() {
        // Initialize all author select fields
        const $authorSelects = $('.schedulely-author-select');

        if ($authorSelects.length && typeof $.fn.select2 === 'function') {
            $authorSelects.select2({
                placeholder: 'Select authors',
                allowClear: true,
                width: '100%'
            });
        }

        // Initialize Select2 for notification users
        const $notificationSelect = $('.schedulely-notification-select');
        if ($notificationSelect.length && typeof $.fn.select2 === 'function') {
            $notificationSelect.select2({
                placeholder: 'Select users to notify',
                allowClear: true,
                width: '100%'
            });
        }
    }

    /**
     * Initialize Flatpickr time pickers
     */
    function initTimePickers() {
        const timeInputs = document.querySelectorAll('.schedulely-timepicker');

        if (!timeInputs.length || typeof flatpickr === 'undefined') {
            return;
        }

        timeInputs.forEach(function (input) {
            flatpickr(input, {
                enableTime: true,
                noCalendar: true,
                dateFormat: 'h:i K',
                time_24hr: false,
                minuteIncrement: 15,
                defaultHour: input.id === 'schedulely_start_time' ? 17 : 23,
                defaultMinute: 0
            });
        });
    }

    /**
     * Toggle author exclusion visibility based on randomize checkbox
     */
    $('#schedulely_randomize_authors').on('change', function () {
        const $authorRow = $('#schedulely_excluded_authors').closest('tr');

        if ($(this).is(':checked')) {
            $authorRow.show();
        } else {
            $authorRow.hide();
        }
    }).trigger('change');

    /**
     * Initialize capacity checker
     */
    function initCapacityChecker() {
        const $startTime = $('#schedulely_start_time');
        const $endTime = $('#schedulely_end_time');
        const $minInterval = $('#schedulely_min_interval');
        const $postsPerDay = $('#schedulely_posts_per_day');

        if (!$startTime.length || !$endTime.length || !$minInterval.length || !$postsPerDay.length) {
            return;
        }

        let capacityCheckTimeout = null;

        // Check capacity on page load
        checkCapacity();

        // Check capacity when any relevant field changes (with debounce)
        [$startTime, $endTime, $minInterval, $postsPerDay].forEach(function ($field) {
            $field.on('input change', function () {
                clearTimeout(capacityCheckTimeout);

                // Show loading state
                $('#schedulely-capacity-notice').html(
                    '<div class="schedulely-capacity-loading">' +
                    '<span class="spinner is-active" style="float: none; margin: 0;"></span> ' +
                    'Checking capacity...' +
                    '</div>'
                );

                capacityCheckTimeout = setTimeout(checkCapacity, 500);
            });
        });
    }

    /**
     * Check capacity via AJAX
     */
    function checkCapacity() {
        const startTime = $('#schedulely_start_time').val() || '5:00 PM';
        const endTime = $('#schedulely_end_time').val() || '11:00 PM';
        const minInterval = parseInt($('#schedulely_min_interval').val() || 40, 10);
        const postsPerDay = parseInt($('#schedulely_posts_per_day').val() || 8, 10);

        fetch(schedulely_admin.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'schedulely_check_capacity',
                nonce: schedulely_admin.nonce,
                start_time: startTime,
                end_time: endTime,
                min_interval: minInterval,
                posts_per_day: postsPerDay
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayCapacityResult(data.data);
                } else {
                    displayCapacityError(data.data?.message || 'Error checking capacity');
                }
            })
            .catch(error => {
                console.error('Capacity check error:', error);
                displayCapacityError('Error checking capacity');
            });
    }

    /**
     * Display capacity check result
     */
    function displayCapacityResult(capacityData) {
        const $notice = $('#schedulely-capacity-notice');
        const $suggestions = $('#schedulely-capacity-suggestions');
        const $suggestionsList = $('#schedulely-suggestions-list');

        if (!capacityData.valid) {
            $notice.html(
                '<div class="alert-box" style="border-left-color: #d63638; background: #fcf0f1;">' +
                '<div class="alert-icon" style="color: #d63638;"><span class="dashicons dashicons-warning"></span></div>' +
                '<div style="flex: 1;">' +
                '<span class="alert-title">Invalid Settings</span>' +
                '<div class="alert-desc">' + (capacityData.error || 'Your time settings are invalid.') + '</div>' +
                '</div>' +
                '</div>'
            );
            $suggestions.hide();
            return;
        }

        // Calculate capacity percentage for the meter
        const percentage = Math.min(100, Math.round((capacityData.capacity / capacityData.desired_quota) * 100));

        if (capacityData.meets_quota) {
            // Success State
            $notice.html(
                '<div class="alert-box" style="border-left-color: #00a32a; background: #edfaef;">' +
                '<div class="alert-icon" style="color: #00a32a;"><span class="dashicons dashicons-yes-alt"></span></div>' +
                '<div style="flex: 1;">' +
                '<span class="alert-title" style="color: #00a32a;">Target Met</span>' +
                '<div class="alert-desc">' +
                'Your settings can fit approximately <strong>' + capacityData.capacity + ' posts per day</strong>.<br>' +
                '<span style="font-size:11px; color: #646970;">Estimate accounts for random time placement efficiency (~70%).</span>' +
                '</div>' +
                '<div class="capacity-meter" style="background: #ccedd5;"><div class="capacity-fill" style="width: 100%; background: #00a32a;"></div></div>' +
                '</div>' +
                '</div>'
            );
            $suggestions.hide();
        } else {
            // Warning/Error State
            $notice.html(
                '<div class="alert-box">' +
                '<div class="alert-icon">' +
                '<svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>' +
                '</div>' +
                '<div style="flex: 1;">' +
                '<span class="alert-title">Target Not Met</span>' +
                '<div class="alert-desc">Your settings can only fit approximately <strong>' + capacityData.capacity + ' posts</strong>, but you want <strong>' + capacityData.desired_quota + ' posts</strong>.</div>' +
                '<div class="capacity-meter"><div class="capacity-fill" style="width: ' + percentage + '%;"></div></div>' +
                '<div style="font-size: 11px; color: #d63638; margin-top: 3px;">Current Capacity: ' + percentage + '%</div>' +
                '</div>' +
                '</div>'
            );

            // Display suggestions
            if (capacityData.suggestions && capacityData.suggestions.length > 0) {
                let suggestionsHtml = '<h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #646970;">Recommended Fixes</h4>';

                capacityData.suggestions.forEach(function (suggestion, index) {
                    let actionButton = '';

                    if (suggestion.type === 'reduce_interval') {
                        actionButton = '<button type="button" class="btn-apply schedulely-apply-suggestion" ' +
                            'data-type="interval" data-value="' + suggestion.suggested + '">Apply Fix</button>';
                    } else if (suggestion.type === 'reduce_quota') {
                        actionButton = '<button type="button" class="btn-apply schedulely-apply-suggestion" ' +
                            'data-type="quota" data-value="' + suggestion.suggested + '">Apply Fix</button>';
                    } else if (suggestion.type === 'expand_window') {
                        actionButton = '<button type="button" class="btn-apply schedulely-apply-suggestion" ' +
                            'data-type="endtime" data-value="' + suggestion.suggested_end + '">Apply Fix</button>';
                    }

                    suggestionsHtml +=
                        '<div class="suggestion-card" style="margin-bottom: 10px;">' +
                        '<div class="sugg-content">' +
                        '<div class="sugg-title">' + (index + 1) + '. ' + suggestion.label + '</div>' +
                        '<div class="sugg-desc">' + suggestion.message + '</div>' +
                        '</div>' +
                        '<div class="sugg-action">' +
                        actionButton +
                        '</div>' +
                        '</div>';
                });

                $suggestionsList.html(suggestionsHtml);
                $suggestions.show();

                // Attach click handlers to apply buttons
                $('.schedulely-apply-suggestion').on('click', function () {
                    const type = $(this).data('type');
                    const value = $(this).data('value');

                    if (type === 'interval') {
                        $('#schedulely_min_interval').val(value).trigger('change');
                    } else if (type === 'quota') {
                        $('#schedulely_posts_per_day').val(value).trigger('change');
                    } else if (type === 'endtime') {
                        $('#schedulely_end_time').val(value).trigger('change');
                    }

                    // Show feedback
                    $(this).text('Applied!').prop('disabled', true).css('background', '#e5e5e5').css('color', '#888').css('border-color', '#ccc');
                    setTimeout(() => {
                        // We don't really revert 'Applied' state easily because the setting changed, 
                        // forcing a re-check which will likely remove the suggestion if fixed.
                    }, 2000);
                });
            } else {
                $suggestions.hide();
            }
        }
    }

    /**
     * Display capacity check error
     */
    function displayCapacityError(message) {
        $('#schedulely-capacity-notice').html(
            '<div class="alert-box" style="border-left-color: #d63638; background: #fcf0f1;">' +
            '<div class="alert-icon"><span class="dashicons dashicons-warning"></span></div>' +
            '<div style="flex:1;"><span class="alert-title">Error</span><div class="alert-desc">' + message + '</div></div>' +
            '</div>'
        );
        $('#schedulely-capacity-suggestions').hide();
    }

    /**
     * Initialize auto schedule toggle handler
     */
    function initAutoScheduleToggle() {
        const $toggle = $('#schedulely_auto_schedule');
        if (!$toggle.length) {
            return;
        }

        $toggle.on('change', function () {
            const isEnabled = $(this).is(':checked');
            const $toggle = $(this);

            // Disable toggle during save
            $toggle.prop('disabled', true);

            fetch(schedulely_admin.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'schedulely_toggle_auto_schedule',
                    nonce: schedulely_admin.nonce,
                    enabled: isEnabled ? '1' : '0'
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        Swal.fire({
                            title: isEnabled ? 'Auto-Schedule Enabled' : 'Auto-Schedule Disabled',
                            text: data.data.message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });

                        // Reload page to update status display
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        // Revert toggle on error
                        $toggle.prop('checked', !isEnabled);
                        Swal.fire({
                            title: 'Error',
                            text: data.data?.message || 'Failed to update auto-schedule setting.',
                            icon: 'error',
                            confirmButtonColor: '#d63638'
                        });
                    }
                })
                .catch(error => {
                    // Revert toggle on error
                    $toggle.prop('checked', !isEnabled);
                    console.error('Auto schedule toggle error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'An unexpected error occurred. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#d63638'
                    });
                })
                .finally(() => {
                    // Re-enable toggle
                    $toggle.prop('disabled', false);
                });
        });
    }

    /**
     * Form validation before submit
     */
    $('form[method="post"]').on('submit', function (e) {
        const postsPerDay = parseInt($('#schedulely_posts_per_day').val(), 10);
        const minInterval = parseInt($('#schedulely_min_interval').val(), 10);

        // Validate posts per day
        if (postsPerDay < 1 || postsPerDay > 100) {
            e.preventDefault();
            Swal.fire({
                title: 'Validation Error',
                text: 'Posts per day must be between 1 and 100.',
                icon: 'error',
                confirmButtonColor: '#d63638'
            });
            return false;
        }

        // Validate minimum interval
        if (minInterval < 1 || minInterval > 1440) {
            e.preventDefault();
            Swal.fire({
                title: 'Validation Error',
                text: 'Minimum interval must be between 1 and 1440 minutes.',
                icon: 'error',
                confirmButtonColor: '#d63638'
            });
            return false;
        }

        // Check if at least one day is selected
        if ($('input[name="schedulely_active_days[]"]:checked').length === 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Validation Error',
                text: 'Please select at least one active day.',
                icon: 'error',
                confirmButtonColor: '#d63638'
            });
            return false;
        }

        return true;
    });

})(jQuery);
