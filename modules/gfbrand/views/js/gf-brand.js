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
     *
     * Progressive enhancement: the markup ships OPEN (D6) so the content is
     * never unreachable without scripts. When JS runs, it collapses the drawer
     * on load — and only then, so a no-script page shows everything — and
     * takes over the toggle.
     *
     * The transition itself is CSS (grid-template-rows 0fr -> 1fr), which
     * reaches intrinsic height and therefore cannot clip a fifth advisor. JS
     * only flips a class and moves focus; it does no measuring.
     * ========================================================================== */

    /**
     * The standalone rendering (advisors.tpl/partners.tpl) puts the trigger
     * button inside the drawer itself. The homepage's embedded rendering
     * does not (story 1.13): the pillar row supplies the trigger, as a
     * sibling of .gf-trust-drawer, wired to its panel only by
     * aria-controls -> the panel's id. Without this fallback, every
     * homepage "MEET ADVISORS"/"OUR PARTNERS" button did nothing at all.
     */
    function findTriggerFor(drawer, body) {
        var internal = drawer.querySelector('[data-gf-drawer-trigger]');

        if (internal) {
            return internal;
        }

        if (body && body.id) {
            return document.querySelector('[data-gf-drawer-trigger][aria-controls="' + body.id + '"]');
        }

        return null;
    }

    function initTrustDrawer(drawer) {
        var body = drawer.querySelector('.gf-trust-drawer-body');
        var trigger = findTriggerFor(drawer, body);

        if (!trigger || !body) {
            return;
        }

        // The markup is born open; closing it the moment JS runs would
        // otherwise animate through the full 450ms collapse on every page
        // load. Suppressing the transition for one frame turns that into an
        // instant, invisible initial state instead of a flash.
        drawer.classList.add('gf-trust-drawer--no-transition');
        drawer.classList.remove('gf-trust-drawer--nojs');
        drawer.classList.add('gf-trust-drawer--closed');
        trigger.setAttribute('aria-expanded', 'false');
        // Force a reflow so the class above is committed before transitions
        // are re-enabled on the next tick.
        // eslint-disable-next-line no-unused-expressions
        drawer.offsetHeight;
        window.setTimeout(function () {
            drawer.classList.remove('gf-trust-drawer--no-transition');
        }, 0);

        function isOpen() {
            return drawer.classList.contains('gf-trust-drawer--open');
        }

        function setOpen(open) {
            drawer.classList.toggle('gf-trust-drawer--open', open);
            drawer.classList.toggle('gf-trust-drawer--closed', !open);
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                // Focus moves into the drawer (AC-5): the first focusable
                // content element, or the region itself. tabindex="-1" is
                // only ever added to the region — stamping it on a real
                // content link would remove that link from the Tab order
                // for good, since it is never restored.
                var focusable = body.querySelector('a[href], button, [tabindex="0"]');
                if (focusable) {
                    focusable.focus({ preventScroll: true });
                } else {
                    body.setAttribute('tabindex', '-1');
                    body.focus({ preventScroll: true });
                }
                drawer.scrollIntoView({ behavior: 'auto', block: 'nearest' });
            }
        }

        trigger.addEventListener('click', function () {
            setOpen(!isOpen());
        });

        // Escape closes and returns focus to the trigger (AC-5) — but only
        // when this drawer is the one that has focus. A single document
        // listener per drawer otherwise means Escape anywhere on the page
        // (another widget, an unrelated form) closes every open drawer, and
        // with more than one open, focus lands on whichever trigger's
        // listener happened to run last.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isOpen() && drawer.contains(document.activeElement)) {
                setOpen(false);
                trigger.focus({ preventScroll: true });
            }
        });
    }

    function initTrustDrawers() {
        var drawers = document.querySelectorAll('.gf-trust-drawer');
        for (var i = 0; i < drawers.length; i++) {
            initTrustDrawer(drawers[i]);
        }
    }


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
            initTrustDrawers();
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
