/**
 * GF Experiences Brand JavaScript
 *
 * Drawer animations, country filter transitions, accessibility helpers.
 * Loaded on every front-office page via hookActionFrontControllerSetMedia.
 *
 * Initially minimal — section scaffolds for the stories that fill them.
 *
 * LAYER 1 (module JS injection)
 */

(function () {
    'use strict';

    /* ==========================================================================
     * Brand asset pipeline — Story 1.2
     * FR-3: Logo served from module, not theme img/
     * FR-4: Logo rendered from 1x/2x derivatives with srcset
     * ========================================================================== */

    /**
     * Replace the theme's default logo (img/logo.jpg) with our branded
     * derivatives. Runs after DOM ready so the header <img> exists.
     */
    function replaceLogo() {
        var logoImg = document.querySelector('#header_logo .logo');
        if (!logoImg) return;

        // Determine module asset path from the page (works with any base URI)
        var cssFiles = document.querySelectorAll('link[rel="stylesheet"]');
        var baseUrl = '/modules/gfbrand/assets/';
        for (var i = 0; i < cssFiles.length; i++) {
            var href = cssFiles[i].getAttribute('href');
            if (href && href.indexOf('/modules/gfbrand/') !== -1) {
                baseUrl = href.substring(0, href.indexOf('views/')) + 'assets/';
                break;
            }
        }

        logoImg.srcset = baseUrl + 'logo-1x.png 1x, ' + baseUrl + 'logo-2x.png 2x';
        logoImg.src = baseUrl + 'logo-1x.png';
        logoImg.alt = logoImg.alt || 'GF Experiences';
    }

    if (typeof window.addEventListener === 'function') {
        window.addEventListener('DOMContentLoaded', replaceLogo);
    }


    /* ==========================================================================
     * Accessibility — skip links and focus management
     * ========================================================================== */

    /**
     * Move focus to an element without triggering :focus-visible outline
     * on browsers that support it. Used when programmatically navigating
     * (e.g., drawer open/close).
     */
    function moveFocusWithoutOutline(selector) {
        var el = document.querySelector(selector);
        if (el) {
            // Suppress focus outline for programmatic moves
            if (typeof el.focus === 'function') {
                // Some browsers respect this, others don't — graceful degradation
                el.setAttribute('tabindex', '-1');
                el.focus({ preventScroll: false });
            }
        }
    }

    /* ==========================================================================
     * Advisor/Partner drawers — Story 1.11
     * grid-rows 0fr -> 1fr animation with keyboard/screen-reader support
     * ========================================================================== */

    // TO BE ADDED BY STORY 1.11


    /* ==========================================================================
     * Country filter pills — Story 1.9
     * Client-side transition on top of server-side filtering
     * ========================================================================== */

    // TO BE ADDED BY STORY 1.9


    /* ==========================================================================
     * Establishment card hover effects — Story 1.10
     * ========================================================================== */

    // TO BE ADDED BY STORY 1.10


    /* ==========================================================================
     * Booking questionnaire — Story 1.12
     * Conditional establishment select, live character counters, validation
     *
     * AC-10: everything here is additive. The country and establishment
     * selects already work — every establishment is a real, submittable
     * <option> from first render (AC-3) — with no script at all; this only
     * narrows which of those options are visible once a country is chosen,
     * which is what makes "JavaScript disabled" and "no country chosen yet"
     * look like the exact same, correct state: every establishment shown.
     * ========================================================================== */

    function initInquiryEstablishmentFilter() {
        var countrySelect = document.querySelector('[data-gf-country-select]');
        var establishmentSelect = document.querySelector('[data-gf-establishment-select]');

        if (!countrySelect || !establishmentSelect) {
            return;
        }

        var options = Array.prototype.slice.call(establishmentSelect.options);

        function applyFilter() {
            var country = countrySelect.value;

            options.forEach(function (option) {
                if (!option.value) {
                    return; // "Not sure yet" always stays.
                }

                var matches = !country || option.getAttribute('data-gf-country') === country;
                option.hidden = !matches;

                if (!matches && option.selected) {
                    establishmentSelect.value = '';
                }
            });
        }

        countrySelect.addEventListener('change', applyFilter);
        applyFilter();
    }

    function initInquiryReferralOther() {
        var select = document.querySelector('[data-gf-referral-select]');
        var otherField = document.querySelector('.gf-referral-other');

        if (!select || !otherField) {
            return;
        }

        function toggle() {
            var isOther = select.value === 'other';
            otherField.hidden = !isOther;
        }

        select.addEventListener('change', toggle);
        toggle();
    }

    if (typeof window.addEventListener === 'function') {
        window.addEventListener('DOMContentLoaded', function () {
            initInquiryEstablishmentFilter();
            initInquiryReferralOther();
        });
    }


    /* ==========================================================================
     * Honeypot spam protection (forms) — Story 1.12
     * Hidden field that bots fill but humans never see
     *
     * Nothing to add here: the honeypot (.gf-hp) is hidden by CSS alone
     * (position off-screen, no display:none — see gf-brand.css §6) and
     * rejected server-side in controllers/front/inquiry.php. A CSS-only,
     * server-checked honeypot needs no script, which also means it still
     * works — and still catches bots — with JavaScript disabled.
     * ========================================================================== */

})();
