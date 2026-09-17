{*
 * GF Experiences — Contact Us live character-count announcement.
 * Story 1.14, AC-6.
 *
 * Fires from the theme's own displayContactFormFieldsAfter hook point,
 * immediately after the #message textarea (READ ONLY:
 * hotel-reservation-theme/contact-form.tpl:174). The region ships empty —
 * gf-brand.js's initContactCharacterCount() fills it in as the visitor
 * types, throttled rather than announced on every keystroke.
 *
 * No maxlength is enforced here or in ContactController: this only tells a
 * screen-reader user how much they have typed, it does not cap them (see
 * the story's "Never" boundary).
 *
 * With JavaScript disabled the element is present but stays empty and
 * silent — the form still submits normally either way (edge-case matrix).
 *}
<p id="gf-contact-char-count" class="gf-contact-char-count" aria-live="polite" role="status"></p>
