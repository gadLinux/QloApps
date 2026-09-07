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
        'id_product' => 'Establishment',
        'travel_date' => 'Travel date',
        'adults' => 'Adults',
        'children' => 'Children',
        'referral_source_other' => 'Where did you find out about us?',
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

        $this->renderForm(new GFInquiryValidationResult(), $this->prefillFromHotelParam());
    }

    /**
     * @return bool
     */
    private function isPost()
    {
        return Tools::getIsset('submitGfInquiry');
    }

    private function handleSubmission()
    {
        // Honeypot (AC-7): a bot fills every field, including the one no
        // human ever sees. Pretend success rather than reveal the trap.
        if (trim((string) Tools::getValue(self::HONEYPOT_FIELD)) !== '') {
            $this->redirectToThankYou();

            return;
        }

        $input = $_POST;
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

        $this->persist($result);
        $this->notifyTeam($result);
        $this->redirectToThankYou();
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

        $inquiry->add();
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
            'Party' => trim($result->get('adults') . ' adults, ' . $result->get('children') . ' children'),
            'Message' => $result->get('message') ?: '(none)',
        ];

        $body = '';
        foreach ($lines as $label => $value) {
            $body .= $label . ': ' . $value . "\n";
        }

        Mail::Send(
            (int) $this->context->language->id,
            'contact',
            'New booking enquiry',
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

        $this->context->smarty->assign([
            'gf_inquiry_sent' => false,
            'gf_input' => array_merge($this->emptyInput(), $input),
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
            'gf_form_action' => $this->context->link->getModuleLink('gfbrand', 'inquiry'),
            'gf_consent_text' => $this->consentText(),
            'gf_days' => range(1, 31),
            'gf_months' => range(1, 12),
            'gf_years' => range(GFInquiryValidator::MIN_TRAVEL_YEAR, GFInquiryValidator::MAX_TRAVEL_YEAR),
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
            'id_gf_partner', 'promo_code', 'dest_country', 'id_product',
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
     * a literal list. 1.11 has not landed yet, so this degrades to an empty
     * list — the field is optional, and it is better to offer nothing than
     * to invent placeholder partners. The query already matches the table
     * 1.11's own story names; no code here will need to change when it ships.
     *
     * @return array[] ['id_gf_partner' => int, 'name' => string]
     */
    private function partnerOptions()
    {
        $schema = new GFSchemaHelper();

        if (!$schema->tableExists('gf_partner')) {
            return [];
        }

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_gf_partner`, `name` FROM `' . _DB_PREFIX_ . 'gf_partner` ORDER BY `name` ASC'
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
        return $this->module->l(
            'I agree with the Terms of use & with passing on my details to local partners',
            'inquiry'
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
