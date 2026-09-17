<?php
/**
 * 2026 GF Experiences
 *
 * The booking questionnaire — story 1.12, FR-18 successor to the live
 * "Internal Booking Questionnaire". Public, unauthenticated: every rule
 * GFInquiryValidator enforces is the real rule, not a convenience the
 * browser already checked (Dev Notes, "Server-side validation is the source
 * of truth").
 *
 * WHY A MODULE FRONT CONTROLLER
 *
 * Same reasoning as story 1.9's establishments controller: this is not core
 * QloApps behaviour, it owns its own table, and nothing here needs a stock
 * controller extended or overridden. Layer 1 throughout.
 *
 * PRESENTATION LAYER — resolves the request, asks GFInquiryValidator whether
 * it is acceptable, and either persists through GfInquiry or re-renders with
 * errors. It holds no validation policy of its own.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class GfbrandInquiryModuleFrontController extends ModuleFrontController
{
    /** Establishment id carried from an "INQUIRE TO BOOK" link — AC-2. */
    const HOTEL_PARAM = 'hotel';

    /** Set on the redirect after a successful submission (PRG). */
    const SENT_PARAM = 'sent';

    /** Never shown to a real visitor: CSS pushes it off-screen (AC-7). */
    const HONEYPOT_FIELD = 'gf_hp_website';

    /** AC-7: accepted submissions in the trailing window, per signal. */
    const RATE_LIMIT_WINDOW_MINUTES = 60;
    const RATE_LIMIT_MAX_PER_IP = 5;
    const RATE_LIMIT_MAX_PER_EMAIL = 3;

    /**
     * "Where did you find out about us?" — AC-9: a select with an Other
     * escape hatch, not free text. Unlike the country and establishment
     * lists (AC-3), these are fixed marketing-channel categories with no
     * establishment-table equivalent to derive them from, so — unlike those —
     * hardcoding them is the correct choice, not the anti-pattern the Dev
     * Notes warn about.
     */
    const REFERRAL_SOURCES = [
        'search_engine' => 'A search engine',
        'social_media' => 'Social media',
        'friend' => 'A friend or family member',
        'advisor' => 'A travel advisor',
        'partner' => 'A partner organization',
        'press' => 'Press or a blog',
    ];

    /** Human labels for the error summary — never the raw field key (AC-5). */
    const FIELD_LABELS = [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email Address',
        'home_country' => 'Home Country',
        'id_gf_partner' => 'Select Partner',
        'promo_code' => 'Promo Code',
        'dest_country' => 'Country',
        'dest_country_other' => 'Destination',
        'id_product' => 'Establishment',
        'travel_date' => 'Travel date',
        'duration' => 'Length of stay',
        'adults' => 'Adults',
        'children' => 'Children',
        'referral_source' => 'Where did you find out about us?',
        'referral_source_other' => 'Where did you find out about us?',
        'message' => 'Message',
        'terms' => 'Terms agreement',
    ];

    /** @var bool Public form; no customer context required. */
    public $auth = false;

    /** @var bool */
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        if (Tools::getIsset(self::SENT_PARAM)) {
            $this->renderThankYou();

            return;
        }

        if ($this->isPost()) {
            $this->handleSubmission();

            return;
        }

        $this->renderForm(new GFInquiryValidationResult(), $this->detectHomeCountry(
            $this->applySearchPreference($this->prefillFromHotelParam())
        ));
    }

    /**
     * @return bool
     */
    private function isPost()
    {
        // Tools::getIsset() is true for a GET parameter too — checking the
        // actual request method is what keeps a crafted link (or an <img>
        // tag) with ?submitGfInquiry=1 from reaching the "POST" path at all.
        return $_SERVER['REQUEST_METHOD'] === 'POST' && Tools::getIsset('submitGfInquiry');
    }

    private function handleSubmission()
    {
        // The form is public, but that is exactly why it needs a token: with
        // none, any page that can make a visitor's browser POST here (a
        // hostile form on another site) submits an enquiry as them.
        if (!Tools::getToken(false) || Tools::getValue('token') !== Tools::getToken(false)) {
            Tools::redirect('index.php?controller=404');

            return;
        }

        // Honeypot (AC-7): a bot fills every field, including the one no
        // human ever sees. Pretend success rather than reveal the trap —
        // but still keep a record: a false positive (autofill on an
        // off-screen field is a real occurrence) is otherwise
        // indistinguishable from a lost enquiry, exactly the failure this
        // table exists to fix. Flagged, not treated as a real submission —
        // it does not count toward the rate limit and is not what a
        // "New" enquiry in the inbox means.
        if (trim((string) Tools::getValue(self::HONEYPOT_FIELD)) !== '') {
            $this->persistSuspectedSpam();
            $this->redirectToThankYou();

            return;
        }

        $input = array_map(function ($value) {
            // Tools::getValue() would silently drop these to '', hiding a
            // spoofed array field instead of rejecting it: coerce here so
            // GFInquiryValidator's trim()/strlen() calls never receive
            // anything but a string (an array posted as first_name[]=a
            // would otherwise become the literal string "Array" once
            // PHP 8 coerces it, past every length and content check).
            return is_scalar($value) ? (string) $value : '';
        }, $_POST);
        $context = $this->validationContext();

        $result = (new GFInquiryValidator())->validate($input, $context);

        if ($result->isValid() && $this->isRateLimited($result)) {
            // Not a field error: nothing the guest typed was wrong. Recorded
            // against no single field so it renders as a form-level notice.
            $result->addError('_form', 'rate_limited');
        }

        if (!$result->isValid()) {
            $this->renderForm($result, $input);

            return;
        }

        if (!$this->persist($result)) {
            // Everything the guest typed passed validation; the failure is
            // ours (a DB error, or a value that cleared GFInquiryValidator
            // but still failed one of GfInquiry's own ObjectModel rules).
            // Either way, nothing was stored — a "Thank you" here would be
            // exactly the silent loss this story exists to fix.
            $result->addError('_form', 'save_failed');
            $this->renderForm($result, $input);

            return;
        }

        $this->notifyTeam($result);
        $this->redirectToThankYou();
    }

    /**
     * Best-effort record of a honeypot trip: the whole point is to keep
     * *something* rather than nothing, so this never runs the guest-facing
     * validator (a bot's junk failing it would defeat the purpose) and
     * never lets a persistence failure interrupt the response — a false
     * positive here is disappointing, but it must never be worse than the
     * silent discard it replaces.
     */
    private function persistSuspectedSpam()
    {
        try {
            $inquiry = new GfInquiry();
            $inquiry->first_name = $this->safeGenericName(Tools::getValue('first_name'), 128) ?: 'Unknown';
            $inquiry->last_name = $this->safeGenericName(Tools::getValue('last_name'), 128) ?: 'Unknown';
            $email = trim((string) Tools::getValue('email'));
            $inquiry->email = Validate::isEmail($email) ? Tools::substr($email, 0, 255) : 'unknown@invalid.example';
            $inquiry->consent_text = $this->consentText();
            $inquiry->consent_at = date('Y-m-d H:i:s');
            $inquiry->status = GfInquiry::STATUS_NEW;
            $inquiry->ip_address = Tools::getRemoteAddr();
            $inquiry->is_suspected_spam = true;

            $inquiry->add();
        } catch (PrestaShopException $e) {
            PrestaShopLogger::addLog(
                '[gfbrand] suspected-spam booking inquiry not saved: ' . $e->getMessage(),
                2,
                null,
                'Module',
                (int) $this->module->id
            );
        }
    }

    /**
     * @param  mixed $value
     * @param  int   $maxLength
     * @return string '' when the value cannot be made to satisfy
     *                 isGenericName even after trimming to length.
     */
    private function safeGenericName($value, $maxLength)
    {
        $value = Tools::substr(trim((string) $value), 0, $maxLength);

        return Validate::isGenericName($value) ? $value : '';
    }

    /**
     * @return bool
     */
    private function isRateLimited(GFInquiryValidationResult $result)
    {
        $ip = Tools::getRemoteAddr();
        $email = (string) $result->get('email');

        if (GfInquiry::countRecentByIp($ip, self::RATE_LIMIT_WINDOW_MINUTES) >= self::RATE_LIMIT_MAX_PER_IP) {
            return true;
        }

        return GfInquiry::countRecentByEmail($email, self::RATE_LIMIT_WINDOW_MINUTES) >= self::RATE_LIMIT_MAX_PER_EMAIL;
    }

    /**
     * @return bool True if the row was actually written.
     */
    private function persist(GFInquiryValidationResult $result)
    {
        $inquiry = new GfInquiry();
        $inquiry->first_name = $result->get('first_name');
        $inquiry->last_name = $result->get('last_name');
        $inquiry->email = $result->get('email');
        $inquiry->phone = $result->get('phone');
        $inquiry->home_country = $result->get('home_country');
        $inquiry->home_city = $result->get('home_city');
        $inquiry->id_gf_partner = $result->get('id_gf_partner');
        $inquiry->promo_code = $result->get('promo_code');
        $inquiry->dest_country = $result->get('dest_country');
        $inquiry->dest_country_other = $result->get('dest_country_other');
        $inquiry->id_product = $result->get('id_product');
        $inquiry->travel_date = $result->get('travel_date');
        $inquiry->duration = $result->get('duration');
        $inquiry->adults = $result->get('adults');
        $inquiry->children = $result->get('children');
        $inquiry->best_time_call = $result->get('best_time_call');
        $inquiry->referral_source = $result->get('referral_source');
        $inquiry->message = $result->get('message');
        // GDPR (Dev Notes): the wording as displayed, not merely a flag —
        // proves what this specific submitter actually agreed to even if the
        // copy on the page changes later.
        $inquiry->consent_text = $this->consentText();
        $inquiry->consent_at = date('Y-m-d H:i:s');
        $inquiry->status = GfInquiry::STATUS_NEW;
        $inquiry->ip_address = Tools::getRemoteAddr();

        try {
            return (bool) $inquiry->add();
        } catch (PrestaShopException $e) {
            PrestaShopLogger::addLog(
                '[gfbrand] booking inquiry not saved: ' . $e->getMessage(),
                3,
                null,
                'Module',
                (int) $this->module->id
            );

            return false;
        }
    }

    /**
     * The team notification. Reuses PrestaShop's own 'contact' mail
     * template rather than a new gfbrand-owned one: story 1.7 (transactional
     * email rebrand) is the story that builds this module's branded mail
     * templates, and it has not landed yet. Building that infrastructure
     * here would be scope creep onto 1.7's work for a notification aimed at
     * staff, not a guest, where the branding stakes are low.
     */
    private function notifyTeam(GFInquiryValidationResult $result)
    {
        $to = Configuration::get('GFBRAND_CONTACT_EMAIL');

        if (!$to) {
            $to = Configuration::get('PS_SHOP_EMAIL');
        }

        if (!$to) {
            return;
        }

        $lines = [
            'Name' => $result->get('first_name') . ' ' . $result->get('last_name'),
            'Email' => $result->get('email'),
            'Phone' => $result->get('phone'),
            'Destination' => $result->get('dest_country'),
            'Travel date' => $result->get('travel_date') ?: 'Not specified',
            'Party' => ($result->get('adults') !== null || $result->get('children') !== null)
                ? ((int) $result->get('adults')) . ' adults, ' . ((int) $result->get('children')) . ' children'
                : 'Not specified',
            'Message' => $result->get('message') ?: '(none)',
        ];

        $body = '';
        foreach ($lines as $label => $value) {
            $body .= $label . ': ' . $value . "\n";
        }

        $sent = Mail::Send(
            (int) $this->context->language->id,
            'contact',
            $this->module->l('New booking enquiry', 'inquiry'),
            [
                '{contact_content_txt}' => $body,
                '{contact_content_html}' => nl2br(Tools::htmlentitiesUTF8($body)),
            ],
            $to,
            null,
            null,
            null,
            null,
            null,
            _PS_MAIL_DIR_,
            false,
            null,
            null,
            $result->get('email')
        );

        if (!$sent) {
            // The row is already saved by this point (persist() runs
            // first) — a failed notification must not look like a lost
            // enquiry in the logs the way a failed persist() would.
            PrestaShopLogger::addLog(
                '[gfbrand] booking inquiry notification email not sent to ' . $to,
                2,
                null,
                'Module',
                (int) $this->module->id
            );
        }
    }

    private function redirectToThankYou()
    {
        Tools::redirect($this->context->link->getModuleLink(
            'gfbrand',
            'inquiry',
            [self::SENT_PARAM => 1]
        ));
    }

    private function renderThankYou()
    {
        $this->context->smarty->assign([
            'gf_inquiry_sent' => true,
            'gf_home_url' => $this->context->link->getPageLink('index', true),
        ]);

        $this->setTemplate('inquiry.tpl');
    }

    /**
     * @param  GFInquiryValidationResult $result
     * @param  array                     $input  Submitted (or pre-filled) values.
     */
    private function renderForm(GFInquiryValidationResult $result, array $input)
    {
        $errors = $result->getErrors();
        $validator = new GFInquiryValidator();

        // Done here rather than in the GET prefill chain so it also applies
        // when a failed submission re-renders: that path passes $_POST
        // straight in, and the form always POSTs travel_year — empty string
        // when unchosen — so only seeing blank as "not chosen" is what makes
        // the default survive a validation round-trip.
        $input = $this->defaultTravelYear(array_merge($this->emptyInput(), $input));

        $this->context->smarty->assign([
            'gf_inquiry_sent' => false,
            'gf_input' => $input,
            'gf_errors' => $errors,
            'gf_field_labels' => self::FIELD_LABELS,
            // AC-5, without JavaScript: the browser autofocuses whichever
            // input carries the autofocus attribute, so the first invalid
            // field is where focus lands on the very page load that shows
            // the error, no script required.
            'gf_first_invalid_field' => $result->firstInvalidField(),
            'gf_home_countries' => $this->homeCountryOptions(),
            'gf_dest_countries' => $this->establishmentRepository()->findCountries(),
            'gf_establishments' => $this->establishmentRepository()->findAllForSelect(
                (int) $this->context->language->id
            ),
            'gf_partners' => $this->partnerOptions(),
            'gf_referral_sources' => self::REFERRAL_SOURCES,
            'gf_honeypot_field' => self::HONEYPOT_FIELD,
            'gf_token' => Tools::getToken(false),
            'gf_form_action' => $this->context->link->getModuleLink('gfbrand', 'inquiry'),
            'gf_consent_text' => $this->consentText(),
            'gf_days' => range(1, 31),
            'gf_months' => range(1, 12),
            'gf_years' => range($validator->minTravelYear(), $validator->maxTravelYear()),
        ]);

        $this->setTemplate('inquiry.tpl');
    }

    /**
     * GET-only pre-fill from an establishment's "INQUIRE TO BOOK" link — AC-2.
     * The country pre-selection reads the establishment's own country out of
     * the same list the form renders, rather than a second query: one source
     * of truth for what the guest sees and what pre-fills it.
     *
     * @return array Shaped like $_POST, so renderForm() can treat both the
     *                same way.
     */
    /**
     * Every field name the template compares $gf_input against, defaulted to
     * ''. Without this, an unset key (any field on the first GET, or one a
     * partial POST simply never sent) is a PHP 8 "Undefined array key"
     * notice on every {if $gf_input.field == ...} comparison — harmless to
     * the comparison itself (an unset key reads as null either way), but
     * noisy in the logs on every single page view.
     *
     * @return array<string, string>
     */
    private function emptyInput()
    {
        return array_fill_keys([
            'first_name', 'last_name', 'email', 'phone', 'home_country', 'home_city',
            'id_gf_partner', 'promo_code', 'dest_country', 'dest_country_other', 'id_product',
            'travel_day', 'travel_month', 'travel_year', 'duration', 'adults', 'children',
            'best_time_call', 'referral_source', 'referral_source_other', 'message', 'terms',
        ], '');
    }

    private function prefillFromHotelParam()
    {
        $idHotel = (int) Tools::getValue(self::HOTEL_PARAM);

        if ($idHotel <= 0) {
            return [];
        }

        foreach ($this->establishmentRepository()->findAllForSelect((int) $this->context->language->id) as $establishment) {
            if ((int) $establishment['id_product'] === $idHotel) {
                return [
                    'id_product' => $idHotel,
                    'dest_country' => $establishment['gf_country'],
                ];
            }
        }

        return [];
    }

    /**
     * Fills travel date and party size from the visitor's last search — the
     * same GFSearchPreference story 1.5 built to refill the booking panel.
     * A guest who arrives at "Inquire to Book" from a hotel page they were
     * just searching dates and occupancy on should not have to type either
     * again; this is the same "enter the minimum" reasoning that already
     * justified passing the establishment id through in the URL.
     *
     * Gaps only, same policy GFSearchMemory itself uses: nothing here
     * overwrites what prefillFromHotelParam() (or, on a re-render, the
     * guest's own prior input) already put in $input. Dates and party size
     * are trip-level facts, not hotel-specific, so they are filled
     * regardless of which hotel the preference remembers — unlike
     * dest_country/id_product, which the ?hotel= link always wins on.
     *
     * @param  array $input
     * @return array
     */
    private function applySearchPreference(array $input)
    {
        $preference = $this->module->getSearchPreferenceRepository()->find();

        if ($preference === null) {
            return $input;
        }

        if ($preference->datesAreStale(date('Y-m-d'))) {
            // A check-in that has already passed is not a date to offer back.
            $preference = $preference->withoutDates();
        }

        if ($preference->hasDates() && !isset($input['travel_day'])) {
            $checkIn = date_create($preference->dateFrom);
            $checkOut = date_create($preference->dateTo);

            if ($checkIn) {
                $input['travel_day'] = (int) $checkIn->format('j');
                $input['travel_month'] = (int) $checkIn->format('n');
                $input['travel_year'] = (int) $checkIn->format('Y');
            }

            if ($checkIn && $checkOut && !isset($input['duration'])) {
                $nights = (int) round(($checkOut->getTimestamp() - $checkIn->getTimestamp()) / 86400);

                if ($nights > 0) {
                    $input['duration'] = $nights . ' ' . ($nights === 1 ? 'day' : 'days');
                }
            }
        }

        if (!empty($preference->occupancies)) {
            if (!isset($input['adults'])) {
                $input['adults'] = $preference->countAdults();
            }

            if (!isset($input['children'])) {
                $input['children'] = $preference->countChildren();
            }
        }

        return $input;
    }

    /**
     * Offers a starting travel year on a fresh form: the current year, i.e.
     * the first year of the window GFInquiryValidator validates against, so
     * a guest who only wants "sometime this year" touches one select, not
     * three — same "enter the minimum" reasoning as every other prefill here.
     * Day and month stay blank: unlike the year they have no sensible
     * default we can guess on the guest's behalf.
     *
     * Gaps only: it fills travel_year just when no more specific source put a
     * year there — the search preference (which sets day, month and year
     * together from a remembered check-in) or a value the guest themselves
     * chose on a previous submission. Called from renderForm() after the
     * merge with emptyInput(), so both the fresh-GET path and the re-render
     * after a failed submission go through it, and "blank" is unambiguous:
     * an empty string, never a missing key.
     *
     * Only defaults the year when day or month is already set. Filling the
     * year alone on an otherwise-untouched form would turn "all three
     * blank" (GFInquiryValidator's one valid "I have no travel date yet"
     * state) into "year set, day and month blank" — which the validator
     * correctly rejects as incomplete — making the optional travel date
     * effectively mandatory for anyone who does not notice the pre-selected
     * year and deliberately clear it.
     *
     * @param  array $input
     * @return array
     */
    private function defaultTravelYear(array $input)
    {
        $dayOrMonthSet = (string) $input['travel_day'] !== '' || (string) $input['travel_month'] !== '';

        if ($dayOrMonthSet && (string) $input['travel_year'] === '') {
            $input['travel_year'] = (int) date('Y');
        }

        return $input;
    }

    /**
     * Defaults Home Country from the browser's Accept-Language header — one
     * fewer field the guest has to touch, in the same "enter only the
     * minimum" spirit as pre-filling the establishment and the last search.
     *
     * This is not new machinery: Tools::getCountry() is core PrestaShop,
     * already enabled in this shop (PS_DETECT_COUNTRY), and already used
     * for exactly this purpose elsewhere (tax/currency defaults at
     * checkout). It reads the same header a browser always sends — no
     * JavaScript, no geolocation permission prompt, nothing to ask the
     * guest for — and falls back to the shop's configured default country
     * when the header is missing or unparseable, so this always proposes
     * something rather than leaving the select on its blank placeholder.
     * It is still only ever a starting point: the field stays a required,
     * fully-editable select, and server-side validation (AC-8) does not
     * trust this any more than a guest's own choice.
     *
     * A more accurate alternative, GeoIP-by-address, is not wired up here:
     * this install has no GeoLite2 database in tools/geoip, so it would be
     * dead code — nothing to look up against — rather than a working
     * feature. Tools::getCountry() already knows how to prefer it
     * ($address argument) if that database is ever added.
     *
     * @param  array $input
     * @return array
     */
    private function detectHomeCountry(array $input)
    {
        if (isset($input['home_country'])) {
            return $input;
        }

        $iso = Country::getIsoById((int) Tools::getCountry());

        if ($iso) {
            $input['home_country'] = $iso;
        }

        return $input;
    }

    /**
     * @return array[] ['iso_code' => string, 'name' => string], AC-8: a real
     *                  country select, not free text.
     */
    private function homeCountryOptions()
    {
        // Every country, not only the ones QloApps allows as a shipping
        // destination (Country's own "active" flag governs sellability, not
        // existence): a guest telling us where they live is not restricted
        // by what this shop can ship an order to.
        $countries = Country::getCountries((int) $this->context->language->id, false);

        usort($countries, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $countries;
    }

    /**
     * "Select Partner" (Dev Notes): source from gf_partner (story 1.11), not
     * a literal list. The table is owned by 1.11, so until it ships this
     * degrades to an empty list — the field is optional, and it is better to
     * offer nothing than to invent placeholder partners.
     *
     * name is a multilang field, so it is read from the _lang table in the
     * current language (1.11's schema is born with the lang split).
     *
     * @return array[] ['id_gf_partner' => int, 'name' => string]
     */
    private function partnerOptions()
    {
        $schema = new GFSchemaHelper();

        if (!$schema->tableExists('gf_partner') || !$schema->tableExists('gf_partner_lang')) {
            return [];
        }

        $idLang = (int) $this->context->language->id;

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT p.`id_gf_partner`, l.`name`
             FROM `' . _DB_PREFIX_ . 'gf_partner` p
             INNER JOIN `' . _DB_PREFIX_ . 'gf_partner_lang` l
                 ON (l.`id_gf_partner` = p.`id_gf_partner` AND l.`id_lang` = ' . $idLang . ')
             WHERE p.`active` = 1
             ORDER BY l.`name` ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * The exact wording the consent checkbox shows. Read by both the
     * template (so the guest sees this precise text) and persist() (so the
     * stored consent_text can never drift from it) — one string, one place.
     *
     * @return string
     */
    private function consentText()
    {
        // Module translation strings come back HTML-entity-escaped
        // (Translate::getModuleTranslation() always runs the result through
        // htmlspecialchars() once a translation file is loaded, regardless
        // of where the string is used) — decoded here so the plain text is
        // what gets stored and what the template's own escape:'htmlall'
        // applies to, exactly once. Without this, "&" was stored and shown
        // as the literal string "&amp;".
        return html_entity_decode(
            $this->module->l(
                'I agree with the Terms of use & with passing on my details to local partners',
                'inquiry'
            ),
            ENT_COMPAT,
            'UTF-8'
        );
    }

    /**
     * @return array<string, mixed> Data the validator checks submissions
     *                               against.
     */
    private function validationContext()
    {
        $idLang = (int) $this->context->language->id;

        return [
            'home_countries' => array_column($this->homeCountryOptions(), 'iso_code'),
            'dest_countries' => $this->establishmentRepository()->findCountries(),
            'establishment_ids' => array_map(
                'intval',
                array_column($this->establishmentRepository()->findAllForSelect($idLang), 'id_product')
            ),
            'partner_ids' => array_map('intval', array_column($this->partnerOptions(), 'id_gf_partner')),
            'referral_sources' => array_keys(self::REFERRAL_SOURCES),
        ];
    }

    /**
     * @return GFEstablishmentRepository
     */
    private function establishmentRepository()
    {
        return $this->module->getEstablishmentRepository();
    }
}
