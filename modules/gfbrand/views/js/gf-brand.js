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
     * ========================================================================== */

    // TO BE ADDED BY STORY 1.12


    /* ==========================================================================
     * Honeypot spam protection (forms) — Story 1.12
     * Hidden field that bots fill but humans never see
     * ========================================================================== */

    // TO BE ADDED BY STORY 1.12

})();
