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

        // The full master list, kept in original order — filtering removes
        // and re-appends real <option> elements rather than setting
        // `hidden` on them: Safari does not honour `hidden` (or `disabled`,
        // visually) on <option>, so a hidden-but-still-selectable option
        // would defeat the whole point of narrowing by country there.
        var options = Array.prototype.slice.call(establishmentSelect.options);

        function applyFilter() {
            var country = countrySelect.value;
            var selectedValue = establishmentSelect.value;
            var selectedStillMatches = false;

            while (establishmentSelect.firstChild) {
                establishmentSelect.removeChild(establishmentSelect.firstChild);
            }

            options.forEach(function (option) {
                // "Not sure yet" always stays.
                var matches = !option.value || !country || option.getAttribute('data-gf-country') === country;

                if (matches) {
                    establishmentSelect.appendChild(option);

                    if (option.value === selectedValue) {
                        selectedStillMatches = true;
                    }
                }
            });

            establishmentSelect.value = selectedStillMatches ? selectedValue : '';
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

    /**
     * Same "Other" escape-hatch pattern as the referral source select, for
     * the destination country: the free text is a regular input, so
     * .hidden here (unlike on an <option>) works the same in every browser.
     */
    function initInquiryDestCountryOther() {
        var select = document.querySelector('[data-gf-country-select]');
        var otherField = document.querySelector('.gf-dest-country-other');

        if (!select || !otherField) {
            return;
        }

        function toggle() {
            var isOther = select.value === 'OTHER';
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
            initInquiryDestCountryOther();
            initContactCharacterCount();
            initFormAccessibility();
        });
    }


    /* ==========================================================================
     * Contact Us character count — Story 1.14, AC-6
     *
     * Progressive enhancement over the theme's native #message field: the
     * region ships empty (contact-char-count.tpl) and this is what fills it
     * in. Debounced, not per keystroke — a fast typist otherwise fires an
     * aria-live announcement on every character, which a screen reader
     * would read as an unusable stream of interruptions. With JavaScript
     * disabled this never runs, the region stays empty and silent, and the
     * form still submits normally (edge-case matrix).
     * ========================================================================== */

    function initContactCharacterCount() {
        var field = document.getElementById('message');
        var region = document.getElementById('gf-contact-char-count');

        if (!field || !region) {
            return;
        }

        var DEBOUNCE_MS = 500;
        var timer = null;

        function announce() {
            timer = null;
            var count = field.value.length;
            region.textContent = count === 1
                ? '1 character'
                : count + ' characters';
        }

        field.addEventListener('input', function () {
            if (timer) {
                window.clearTimeout(timer);
            }
            timer = window.setTimeout(announce, DEBOUNCE_MS);
        });

        // A rejected submission re-renders the form with the guest's own
        // text already in #message (no 'input' event fires for that) —
        // without this the region would stay empty while the field itself
        // is not, understating what's actually in it.
        if (field.value.length > 0) {
            announce();
        }
    }

    /* ==========================================================================
     * Form accessibility — Story 1.6, AC-6 / AC-7
     *
     * PrestaShop's native forms across the transaction flow already render
     * an inline error summary — errors.tpl's `.alert.alert-danger`, either
     * server-rendered on a plain postback (cart, order-address,
     * authentication, identity) or re-injected via AJAX on the one-page
     * checkout (order-opc.js building the same markup into
     * #opc_account_errors / #opc_login_errors / #customer_guest_detail_errors)
     * — but it is not wired to any field: no aria-describedby, no
     * aria-invalid, and focus stays wherever it was before submit. Editing
     * errors.tpl or any address/auth template to add this would violate
     * hooking_guard (never edit a hotel-reservation-theme .tpl); this finds
     * the DOM PrestaShop already renders and wires the ARIA relationship
     * onto it after the fact — the same pattern as initContactCharacterCount
     * above. With JavaScript disabled this never runs: PrestaShop's own
     * error text is still there, still visible, just not ARIA-wired (see
     * the edge-case matrix in spec-1-6-transaction-rebrand.md).
     * ========================================================================== */

    var gfA11yIdCounter = 0;

    function gfA11yNextId(prefix) {
        gfA11yIdCounter += 1;
        return prefix + '-' + gfA11yIdCounter;
    }

    /* Walks up from the error block to the narrowest sensible search scope:
     * the form it belongs to if there is one (most of the transaction
     * flow), else the main content column, so a page-level error (e.g.
     * authentication.tpl's errors.tpl, which sits above both of its forms)
     * never reaches into the header search widget or footer newsletter
     * field looking for "the" invalid field. */
    function gfA11yScopeFor(errorBlock) {
        var node = errorBlock;
        while (node && node.nodeType === 1) {
            if (node.tagName === 'FORM') {
                return node;
            }
            if (node.id === 'center_column' || node.id === 'content') {
                return node;
            }
            node = node.parentElement;
        }
        return errorBlock.parentElement || document;
    }

    /* authentication.tpl's page-level error sits above two independent
     * forms (create-account, sign-in): once the scope walk falls back to
     * center_column/content, it can no longer tell which form the error
     * actually belongs to. Guessing "the first empty required field in
     * DOM order" would as often as not wire up the wrong form entirely —
     * worse than the no-field-found fallback below, which at least
     * announces the message without misdirecting anyone. Only trust the
     * scope when it resolved to the form itself, or to a container with
     * exactly one form in it. */
    function gfA11yScopeIsAmbiguous(scope) {
        return scope.tagName !== 'FORM' && scope.querySelectorAll('form').length > 1;
    }

    /* A field counts as invalid if the theme's own client-side validator
     * (js/validate.js) already flagged its .form-group with .form-error
     * (blur already happened), or — on a fresh server-rendered reload,
     * where that never ran — it is a required field left empty. */
    function gfA11yIsUnanswered(field) {
        if (field.tagName === 'SELECT') {
            var selected = field.options[field.selectedIndex];
            // A placeholder option ("Choose a country...") with no `value`
            // attribute reads back as non-empty (its text becomes .value
            // per the HTML spec), so native validity never flags it —
            // treat "still on the valueless placeholder" as unanswered.
            if (selected && !selected.hasAttribute('value')) {
                return true;
            }
        }
        return !field.value || (field.willValidate && !field.checkValidity());
    }

    function gfA11yFindInvalidField(scope) {
        var markedGroup = scope.querySelector('.form-group.form-error, .form-error');
        if (markedGroup) {
            var markedField = markedGroup.querySelector('input, select, textarea');
            if (markedField && markedField.type !== 'hidden' && !markedField.disabled) {
                return markedField;
            }
        }

        var candidates = scope.querySelectorAll(
            'input.is_required, select.is_required, textarea.is_required, ' +
            '[data-validate], .required input, .required select, ' +
            '.required textarea, [required]'
        );
        for (var i = 0; i < candidates.length; i++) {
            var field = candidates[i];
            if (field.type === 'hidden' || field.disabled) {
                continue;
            }
            // Required-but-empty, still on a valueless placeholder option,
            // or a value present that already fails the browser's own
            // constraint validation (e.g. type="email" with
            // data-validate="isEmail" holding "not-an-email") — a fresh
            // server-rendered reload never ran validate.js's blur handler,
            // so .form-error above is the only other source of this signal.
            if (gfA11yIsUnanswered(field)) {
                return field;
            }
        }
        return null;
    }

    function gfA11yWireErrorBlock(errorBlock) {
        if (!errorBlock) {
            return;
        }

        // Re-wire whenever the message actually changes (first render, or
        // a fresh AJAX response reusing the same container) but skip
        // redundant work — and redundant focus-stealing — otherwise.
        var text = errorBlock.textContent || '';

        // order-opc's guest/login/account error containers, and
        // authentication.tpl's own #create_account_error, ship as an
        // empty, display:none placeholder that PrestaShop's own JS fills
        // in later. An empty block is not an active error: wiring one up
        // would steal focus into a form field on an ordinary page load
        // where nothing has actually gone wrong yet.
        if (!text.trim()) {
            return;
        }

        if (errorBlock.getAttribute('data-gf-a11y-text') === text) {
            return;
        }
        errorBlock.setAttribute('data-gf-a11y-text', text);

        if (!errorBlock.id) {
            errorBlock.id = gfA11yNextId('gf-form-errors');
        }
        if (!errorBlock.hasAttribute('role')) {
            errorBlock.setAttribute('role', 'alert');
        }

        var scope = gfA11yScopeFor(errorBlock);
        var field = gfA11yScopeIsAmbiguous(scope) ? null : gfA11yFindInvalidField(scope);

        if (field) {
            if (!field.id) {
                field.id = gfA11yNextId('gf-field');
            }
            var described = field.getAttribute('aria-describedby');
            if (!described) {
                field.setAttribute('aria-describedby', errorBlock.id);
            } else if (described.indexOf(errorBlock.id) === -1) {
                field.setAttribute('aria-describedby', described + ' ' + errorBlock.id);
            }
            field.setAttribute('aria-invalid', 'true');
            field.focus();
        } else {
            // No single field could be identified (e.g. a page-level or
            // carrier/TOS error) — move focus to the message itself so a
            // screen reader still announces it rather than leaving focus
            // stranded on whatever was last clicked.
            if (!errorBlock.hasAttribute('tabindex')) {
                errorBlock.setAttribute('tabindex', '-1');
            }
            errorBlock.focus();
        }
    }

    function gfA11yScanForErrorBlocks(root) {
        var blocks = root.querySelectorAll('.alert.alert-danger');
        for (var i = 0; i < blocks.length; i++) {
            gfA11yWireErrorBlock(blocks[i]);
        }
    }

    function initFormAccessibility() {
        gfA11yScanForErrorBlocks(document);

        if (typeof MutationObserver !== 'function') {
            return;
        }

        // One observer covers both concerns: (1) an error summary rendered
        // or replaced later by AJAX (order-opc.js, authentication.js), and
        // (2) validate.js's per-field .form-error/.form-ok toggle on blur,
        // mirrored onto aria-invalid so a screen reader gets the same
        // signal a sighted guest gets from the border/icon colour change.
        var observer = new MutationObserver(function (mutations) {
            gfA11yScanForErrorBlocks(document);

            for (var i = 0; i < mutations.length; i++) {
                var target = mutations[i].target;
                if (!target || target.nodeType !== 1 || !target.classList) {
                    continue;
                }
                if (!target.classList.contains('form-group')) {
                    continue;
                }
                var input = target.querySelector('input, select, textarea');
                if (!input) {
                    continue;
                }
                if (target.classList.contains('form-error')) {
                    input.setAttribute('aria-invalid', 'true');
                } else if (target.classList.contains('form-ok')) {
                    input.setAttribute('aria-invalid', 'false');
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
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
