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
        initTabs();
        initScheduleButton();
        initTimeValidation();
        initAuthorSelect();
        initTimePickers();
        initCapacityChecker();
        initPoolSizeNotice();
        initAutoScheduleToggle();
        initAiTestConnection();
        initModeCards();
        initViewPostsDropdown();
        initAuthorExclusionToggle();
    });

    /**
     * Tab switching for the config card.
     * Reads data-tab on each button, shows/hides matching panels.
     */
    function initTabs() {
        const $btns = $('.schedulely-tab-btn');
        if ( ! $btns.length ) return;

        $btns.on('click', function () {
            const tab = $(this).data('tab');

            // Update buttons
            $btns.removeClass('active').attr('aria-selected', 'false');
            $(this).addClass('active').attr('aria-selected', 'true');

            // Update panels — use prop('hidden') to match the HTML hidden attribute
            $('.schedulely-tab-panel').each(function () {
                $(this).prop('hidden', $(this).attr('id') !== 'sly-tab-' + tab);
            });
        });
    }

    /**
     * Mode card visual selection — keeps border/background in sync
     * when a radio inside a .schedulely-mode-card is changed.
     * (CSS :has() handles modern browsers; this covers older ones.)
     */
    function initModeCards() {
        $('.schedulely-mode-card input[type=radio]').on('change', function () {
            $('.schedulely-mode-card').removeClass('is-selected');
            $(this).closest('.schedulely-mode-card').addClass('is-selected');
        });
    }

    /**
     * Sidebar "View posts" dropdown — navigate on select change.
     */
    function initViewPostsDropdown() {
        $('#schedulely-view-posts-type').on('change', function () {
            const url = $(this).val();
            if ( url ) window.location.href = url;
        });
    }

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
     * Replace %1$s-style placeholders in a localized string.
     *
     * @param {string} template
     * @param {...string} values
     * @return {string}
     */
    function formatI18n(template, ...values) {
        if (!template) {
            return '';
        }
        return template.replace(/%(\d+)\$s/g, function (_match, index) {
            const value = values[parseInt(index, 10) - 1];
            return value !== undefined && value !== null ? String(value) : '';
        });
    }

    /**
     * Current eligible vs pool-size overflow (reads live pool-size input).
     *
     * @return {{eligible: number, poolSize: number, overflow: number}}
     */
    function getPoolOverflowInfo() {
        const notice = document.getElementById('schedulely-pool-notice');
        const poolInput = document.getElementById('schedulely_pool_size');
        const eligible = notice
            ? parseInt(notice.getAttribute('data-available') || '0', 10)
            : (schedulely_admin.pool?.eligible || 0);
        let poolSize = poolInput
            ? parseInt(poolInput.value || '0', 10)
            : (schedulely_admin.pool?.size || 0);

        if (isNaN(poolSize) || poolSize < 1) {
            poolSize = 1;
        }
        if (poolSize > 10000) {
            poolSize = 10000;
        }

        return {
            eligible: eligible,
            poolSize: poolSize,
            overflow: Math.max(0, eligible - poolSize)
        };
    }

    /**
     * Show or hide the pool-overflow banner when Pool Size changes in the form.
     */
    function initPoolSizeNotice() {
        const $input = $('#schedulely_pool_size');
        const $notice = $('#schedulely-pool-notice');
        const $noticeText = $('#schedulely-pool-notice-text');

        if (!$input.length || !$notice.length || !$noticeText.length) {
            return;
        }

        function refreshPoolNotice() {
            const pool = getPoolOverflowInfo();
            const template = schedulely_admin.strings.pool_overflow_notice || '';

            if (pool.overflow > 0 && template) {
                $noticeText.html(
                    formatI18n(
                        template,
                        pool.eligible.toLocaleString(),
                        pool.poolSize.toLocaleString(),
                        pool.overflow.toLocaleString()
                    )
                );
                $notice.prop('hidden', false);
            } else {
                $notice.prop('hidden', true);
            }
        }

        $input.on('input change', refreshPoolNotice);
    }

    /**
     * Show normal schedule confirmation dialog
     */
    function showScheduleConfirmation(button) {
        const pool = getPoolOverflowInfo();
        let bodyHtml = schedulely_admin.strings.schedule_posts_body || 'This will schedule all available posts according to your settings.<br><br><strong>Do you want to continue?</strong>';

        if (pool.overflow > 0) {
            const warning = formatI18n(
                schedulely_admin.strings.pool_overflow_body || '',
                pool.eligible.toLocaleString(),
                pool.poolSize.toLocaleString(),
                pool.overflow.toLocaleString()
            );
            bodyHtml = '<div style="text-align:left;margin-bottom:12px;padding:10px 12px;background:#fcf0f1;border-left:4px solid #d63638;border-radius:2px;">'
                + '<strong>' + (schedulely_admin.strings.pool_overflow_title || 'Pool size limit') + '</strong>'
                + '<p style="margin:6px 0 0;font-size:14px;">' + warning + '</p>'
                + '</div>'
                + bodyHtml;
        }

        Swal.fire({
            title: schedulely_admin.strings.schedule_posts_title || 'Schedule Posts Now?',
            html: bodyHtml,
            icon: pool.overflow > 0 ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: '#2271b1',
            cancelButtonColor: '#d63638',
            confirmButtonText: schedulely_admin.strings.yes_schedule || 'Yes, Schedule Now',
            cancelButtonText: schedulely_admin.strings.cancel || 'Cancel',
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
                        title: schedulely_admin.strings.success || 'Success!',
                        html: data.data.message || 'Posts scheduled successfully!',
                        icon: 'success',
                        confirmButtonColor: '#2271b1',
                        confirmButtonText: schedulely_admin.strings.view_scheduled || 'View Scheduled Posts',
                        showCancelButton: true,
                        cancelButtonText: schedulely_admin.strings.stay_here || 'Stay Here',
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

        // Initialize Select2 for post types
        const $postTypeSelect = $('.schedulely-post-type-select');
        if ($postTypeSelect.length && typeof $.fn.select2 === 'function') {
            $postTypeSelect.select2({
                placeholder: 'Select post types',
                allowClear: false,
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
     * Initialize capacity checker — no layout-shift version.
     *
     * The pill element already exists in the DOM at a fixed height.
     * We only ever swap its text and CSS class; nothing is injected
     * above or below the form, so the page never jumps.
     */
    function initCapacityChecker() {
        const $startTime  = $('#schedulely_start_time');
        const $endTime    = $('#schedulely_end_time');
        const $minInterval  = $('#schedulely_min_interval');
        const $postsPerDay  = $('#schedulely_posts_per_day');

        if (!$startTime.length || !$endTime.length || !$minInterval.length || !$postsPerDay.length) {
            return;
        }

        let capacityCheckTimeout = null;

        // Silent first load — no spinner, just fetch and update the pill.
        checkCapacity();

        // On field change: mark pill as "recalculating" (same size), then debounce.
        [$startTime, $endTime, $minInterval, $postsPerDay].forEach(function ($field) {
            $field.on('input change', function () {
                clearTimeout(capacityCheckTimeout);
                setPillState('checking', schedulely_admin.strings.capacity_checking || 'Recalculating…');
                capacityCheckTimeout = setTimeout(checkCapacity, 600);
            });
        });

        // Suggestions accordion toggle.
        $('#schedulely-capacity-details-toggle').on('click', function () {
            const $details = $('#schedulely-capacity-details');
            const expanded = $(this).attr('aria-expanded') === 'true';
            $(this).attr('aria-expanded', String(!expanded));
            $(this).text(
                !expanded
                    ? (schedulely_admin.strings.capacity_hide_suggestions || 'Hide suggestions')
                    : (schedulely_admin.strings.capacity_show_suggestions || 'Show suggestions')
            );
            $details.prop('hidden', expanded);
        });
    }

    /**
     * Update the capacity pill state without touching anything else in the DOM.
     *
     * @param {string} state  'ok' | 'warn' | 'error' | 'checking'
     * @param {string} text   Label shown inside the pill.
     */
    function setPillState(state, text) {
        const $pill = $('#schedulely-capacity-pill');
        $pill
            .removeClass('is-ok is-warn is-error is-checking')
            .addClass('is-' + state)
            .find('.schedulely-capacity-pill-text')
            .text(text);
    }

    /**
     * Fetch capacity data via AJAX.
     */
    function checkCapacity() {
        const startTime   = $('#schedulely_start_time').val()  || '5:00 PM';
        const endTime     = $('#schedulely_end_time').val()    || '11:00 PM';
        const minInterval = parseInt($('#schedulely_min_interval').val()  || 40, 10);
        const postsPerDay = parseInt($('#schedulely_posts_per_day').val() || 8,  10);

        fetch(schedulely_admin.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action:       'schedulely_check_capacity',
                nonce:        schedulely_admin.nonce,
                start_time:   startTime,
                end_time:     endTime,
                min_interval: minInterval,
                posts_per_day: postsPerDay
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                displayCapacityResult(data.data);
            } else {
                setPillState('error', schedulely_admin.strings.capacity_error || 'Settings error');
                hideSuggestions();
            }
        })
        .catch(() => {
            setPillState('error', schedulely_admin.strings.capacity_error || 'Settings error');
            hideSuggestions();
        });
    }

    /** Hide the suggestions accordion and its toggle button. */
    function hideSuggestions() {
        $('#schedulely-capacity-details').prop('hidden', true);
        $('#schedulely-capacity-details-toggle')
            .prop('hidden', true)
            .attr('aria-expanded', 'false')
            .text(schedulely_admin.strings.capacity_show_suggestions || 'Show suggestions');
    }

    /**
     * Build and show the timezone overlap info panel.
     * Shown when US timezone-aware ordering is on and capacity is met.
     *
     * @param {Object} overlaps  { eastern: {start, end, minutes}, central: ..., ... }
     */
    function buildTimezoneOverlapPanel(overlaps) {
        const $list   = $('#schedulely-suggestions-list');
        const $toggle = $('#schedulely-capacity-details-toggle');
        const labels  = { eastern: 'Eastern', central: 'Central', mountain: 'Mountain', pacific: 'Pacific' };

        let html = '<p style="margin:0 0 10px; font-size:12px; font-weight:700; text-transform:uppercase; color:#646970; letter-spacing:.5px;">🌎 US Timezone Active Windows</p>';
        html += '<p style="font-size:12px; color:#50575e; margin:0 0 12px;">Posts will be scheduled within these overlaps between your publishing window and each timezone\'s active hours (7 AM – 11 PM local time).</p>';
        html += '<table style="width:100%; border-collapse:collapse; font-size:12px;">';
        html += '<thead><tr style="border-bottom:1px solid #f0f0f1;"><th style="text-align:left; padding:4px 8px; color:#646970; font-weight:600;">Timezone</th><th style="text-align:left; padding:4px 8px; color:#646970; font-weight:600;">Window (site time)</th><th style="text-align:right; padding:4px 8px; color:#646970; font-weight:600;">Span</th></tr></thead>';
        html += '<tbody>';

        Object.keys(labels).forEach(function(group) {
            const o = overlaps[group];
            if (!o) return;
            html += '<tr style="border-bottom:1px solid #f9f9f9;">';
            html += '<td style="padding:6px 8px; font-weight:600;">' + labels[group] + '</td>';
            html += '<td style="padding:6px 8px; color:#1d2327;">' + o.start + ' – ' + o.end + '</td>';
            html += '<td style="padding:6px 8px; text-align:right; color:#646970;">' + o.minutes + ' min</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        $list.html(html);
        $toggle
            .prop('hidden', false)
            .attr('aria-expanded', 'false')
            .text(schedulely_admin.strings.capacity_show_suggestions || 'Show timezone windows');
        $('#schedulely-capacity-details').prop('hidden', true);
    }

    /**
     * Update the pill and (optionally) populate the suggestions accordion.
     * Never injects HTML outside the two dedicated elements.
     *
     * @param {Object} capacityData  Response from schedulely_check_capacity.
     */
    function displayCapacityResult(capacityData) {
        if (!capacityData.valid) {
            setPillState('error', capacityData.error || (schedulely_admin.strings.capacity_invalid || 'Invalid settings'));
            hideSuggestions();
            return;
        }

        const capacity   = capacityData.capacity;
        const quota      = capacityData.desired_quota;
        const percentage = Math.min(100, Math.round((capacity / quota) * 100));

        if (capacityData.meets_quota) {
            setPillState('ok',
                (schedulely_admin.strings.capacity_ok || 'Fits quota') +
                ' — ~' + capacity + '/' + quota + ' posts/day'
            );
            if (capacityData.timezone_overlaps) {
                buildTimezoneOverlapPanel(capacityData.timezone_overlaps);
            } else {
                hideSuggestions();
            }
            return;
        }
        setPillState('warn',
            (schedulely_admin.strings.capacity_warn || 'Below quota') +
            ' — ' + capacity + '/' + quota + ' posts/day (' + percentage + '%)'
        );

        const $list   = $('#schedulely-suggestions-list');
        const $toggle = $('#schedulely-capacity-details-toggle');

        let html = '<p style="margin:0 0 10px; font-size:12px; font-weight:700; text-transform:uppercase; color:#646970; letter-spacing:.5px;">' +
            (schedulely_admin.strings.recommended_fixes || 'Recommended Fixes') + '</p>';

        if (capacityData.ai_hint) {
            html += '<p class="schedulely-capacity-ai-hint">🤖 ' + capacityData.ai_hint + '</p>';
        }

        if (capacityData.timezone_overlaps) {
            const labels = { eastern: 'Eastern', central: 'Central', mountain: 'Mountain', pacific: 'Pacific' };
            html += '<p style="margin:0 0 6px; font-size:12px; font-weight:700; text-transform:uppercase; color:#646970; letter-spacing:.5px;">🌎 US Timezone Active Windows</p>';
            html += '<table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:14px;">';
            Object.keys(labels).forEach(function(group) {
                const o = capacityData.timezone_overlaps[group];
                if (!o) return;
                html += '<tr style="border-bottom:1px solid #f9f9f9;"><td style="padding:4px 8px; font-weight:600;">' + labels[group] + '</td><td style="padding:4px 8px;">' + o.start + ' – ' + o.end + '</td><td style="padding:4px 8px; text-align:right; color:#646970;">' + o.minutes + ' min</td></tr>';
            });
            html += '</table>';
        }

        if (capacityData.suggestions && capacityData.suggestions.length) {
            capacityData.suggestions.forEach(function (s, i) {
                let btn = '';
                if (s.type === 'reduce_interval') {
                    btn = '<button type="button" class="btn-apply schedulely-apply-suggestion" data-type="interval" data-value="' + s.suggested + '">Apply</button>';
                } else if (s.type === 'reduce_quota') {
                    btn = '<button type="button" class="btn-apply schedulely-apply-suggestion" data-type="quota" data-value="' + s.suggested + '">Apply</button>';
                } else if (s.type === 'expand_window') {
                    btn = '<button type="button" class="btn-apply schedulely-apply-suggestion" data-type="endtime" data-value="' + s.suggested_end + '">Apply</button>';
                }
                html +=
                    '<div class="suggestion-card" style="margin-bottom:8px;">' +
                    '<div class="sugg-content"><div class="sugg-title">' + (i + 1) + '. ' + s.label + '</div>' +
                    '<div class="sugg-desc">' + s.message + '</div></div>' +
                    '<div class="sugg-action">' + btn + '</div></div>';
            });
        }

        $list.html(html);

        // Re-attach apply handlers (list was just re-rendered).
        $list.find('.schedulely-apply-suggestion').on('click', function () {
            const type  = $(this).data('type');
            const value = $(this).data('value');
            if (type === 'interval') {
                $('#schedulely_min_interval').val(value).trigger('change');
            } else if (type === 'quota') {
                $('#schedulely_posts_per_day').val(value).trigger('change');
            } else if (type === 'endtime') {
                $('#schedulely_end_time').val(value).trigger('change');
            }
            $(this).text('Applied ✓').prop('disabled', true);
        });

        // Show the toggle button (hidden by default when no suggestions exist).
        $toggle
            .prop('hidden', false)
            .attr('aria-expanded', 'false')
            .text(schedulely_admin.strings.capacity_show_suggestions || 'Show suggestions');
        // Keep accordion closed — user opens it intentionally.
        $('#schedulely-capacity-details').prop('hidden', true);
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
                title: schedulely_admin.strings.validation_error || 'Validation Error',
                text: schedulely_admin.strings.posts_per_day_range || 'Posts per day must be between 1 and 100.',
                icon: 'error',
                confirmButtonColor: '#d63638'
            });
            return false;
        }

        // Validate minimum interval
        if (minInterval < 1 || minInterval > 1440) {
            e.preventDefault();
            Swal.fire({
                title: schedulely_admin.strings.validation_error || 'Validation Error',
                text: schedulely_admin.strings.interval_range || 'Minimum interval must be between 1 and 1440 minutes.',
                icon: 'error',
                confirmButtonColor: '#d63638'
            });
            return false;
        }

        // Check if at least one day is selected
        if ($('input[name="schedulely_active_days[]"]:checked').length === 0) {
            e.preventDefault();
            Swal.fire({
                title: schedulely_admin.strings.validation_error || 'Validation Error',
                text: schedulely_admin.strings.select_day || 'Please select at least one active day.',
                icon: 'error',
                confirmButtonColor: '#d63638'
            });
            return false;
        }

        return true;
    });

    /**
     * Test AI API connection from settings (shows loading state on the button).
     */
    function initAiTestConnection() {
        const $btn = $('#schedulely-test-ai-connection');
        if (!$btn.length) {
            return;
        }
        $btn.on('click', function (e) {
            e.preventDefault();
            const $out = $('#schedulely-ai-test-result');
            if (typeof schedulely_admin === 'undefined' || !schedulely_admin.ajaxurl || !schedulely_admin.nonce) {
                return;
            }

            const origHtml = $btn.html();
            const contacting = (schedulely_admin.strings && schedulely_admin.strings.test_ai_contacting)
                ? schedulely_admin.strings.test_ai_contacting
                : (schedulely_admin.strings && schedulely_admin.strings.test_ai_running)
                    ? schedulely_admin.strings.test_ai_running
                    : '…';

            $btn.prop('disabled', true).addClass('schedulely-ai-test-busy');
            $btn.html(
                '<span class="spinner is-active" style="float: none; margin: 0 6px 0 0; vertical-align: middle;"></span>'
                    + '<span class="schedulely-ai-test-btn-label">' + contacting + '</span>'
            );

            if ($out.length) {
                $out
                    .removeClass('schedulely-test-error schedulely-test-ok')
                    .css({ color: '#646970' })
                    .text(contacting)
                    .show();
            }

            const params = new URLSearchParams({
                action: 'schedulely_test_ai_connection',
                nonce: schedulely_admin.nonce
            });
            fetch(schedulely_admin.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: params
            })
                .then((r) => r.json())
                .then((json) => {
                    if (!$out.length) {
                        return;
                    }
                    if (json.success && json.data && json.data.message) {
                        $out
                            .addClass('schedulely-test-ok')
                            .css({ color: '#00a32a' })
                            .text(json.data.message);
                    } else {
                        const msg = (json.data && json.data.message)
                            ? json.data.message
                            : (schedulely_admin.strings.test_ai_fail || 'Error');
                        $out
                            .addClass('schedulely-test-error')
                            .css({ color: '#d63638' })
                            .text(msg);
                    }
                    $out.show();
                })
                .catch(() => {
                    if ($out.length) {
                        $out
                            .addClass('schedulely-test-error')
                            .css({ color: '#d63638' })
                            .text(schedulely_admin.strings.test_ai_fail || 'Error')
                            .show();
                    }
                })
                .finally(() => {
                    $btn.prop('disabled', false).removeClass('schedulely-ai-test-busy').html(origHtml);
                });
        });
    }

    /**
     * Handle welcome notice dismiss button and WP's built-in X button.
     * The nonce is output via wp_add_inline_script as window.schedulely_dismiss_nonce.
     */
    function initWelcomeNoticeDismiss() {
        if ( ! window.schedulely_dismiss_nonce ) {
            return;
        }
        const nonce = window.schedulely_dismiss_nonce;

        function sendDismiss() {
            $.post( ajaxurl, {
                action : 'schedulely_dismiss_notice',
                nonce  : nonce
            } );
        }

        $( document ).on( 'click', '.schedulely-dismiss-notice', function () {
            $( '#schedulely-welcome-notice' ).fadeOut();
            sendDismiss();
        } );

        $( document ).on( 'click', '#schedulely-welcome-notice .notice-dismiss', function () {
            sendDismiss();
        } );
    }

    /**
     * Post-type "View All" dropdown — navigate on change.
     * Replaces the inline onchange="..." attribute removed from PHP markup.
     *
     * @since 1.6.0
     */
    function initPostTypeViewSelect() {
        $( document ).on( 'change', '#schedulely-view-posts-type', function () {
            const url = $( this ).val();
            if ( url ) {
                window.location.href = url;
            }
        } );
    }

    /**
     * Author exclusion show/hide tied to the randomize-authors checkbox.
     * Targets the flex container wrapping the author selects.
     *
     * @since 1.6.0 Fixed selector (was .closest('tr') on a flex layout).
     */
    function initAuthorExclusionToggle() {
        const $checkbox = $( '#schedulely_randomize_authors' );
        if ( ! $checkbox.length ) {
            return;
        }
        const $container = $( '#schedulely_excluded_authors, #schedulely_preserved_authors' )
            .closest( '[style*="flex"]' )
            .parent();

        function toggle() {
            if ( $checkbox.is( ':checked' ) ) {
                $container.show();
            } else {
                $container.hide();
            }
        }

        $checkbox.on( 'change', toggle );
        toggle();
    }

    // Boot the new handlers alongside the existing ones.
    $( document ).ready( function () {
        initWelcomeNoticeDismiss();
        initPostTypeViewSelect();
        initAuthorExclusionToggle();
    } );

})(jQuery);
