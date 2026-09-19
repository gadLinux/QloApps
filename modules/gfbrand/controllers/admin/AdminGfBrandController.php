<?php
/**
 * 2026 GF Experiences
 *
 * The consolidated brand settings screen — story 1.18.
 *
 * Story 1.17's live audit found the owner's editable brand content spread
 * across four separate vendor-module config screens: shop contact
 * (hotelreservationsystem), About copy (wkabouthotelblock), amenities copy
 * (wkhotelfeaturesblock), and testimonials (wktestimonialblock). This screen
 * is a write-through convenience layer over the exact same
 * ps_configuration / ps_configuration_lang keys those modules already read
 * — never a second, gfbrand-owned copy of the same value. Every group in
 * self::VENDOR_FIELD_SPECS mirrors one vendor controller's own
 * fields_options definition field for field, so saving here and opening
 * that vendor screen afterward always agree (story 1.18, AC-4).
 *
 * The featured-establishments picker (AC-5) is intentionally NOT duplicated
 * here: story 1.17 already built it as its own screen
 * (AdminGfEstablishmentsController), and re-rendering that same
 * checkbox list a second time would itself become a second source of
 * truth risk. This screen links out to it instead.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminGfBrandController extends ModuleAdminController
{
    /**
     * The vendor-key contract. Every entry is exactly the field spec a
     * vendor controller already uses (type/lang/validation/required),
     * minus the translated title/hint strings, which need `$this->l()` and
     * are layered on at construct time by fieldsOptionsFor().
     *
     * This constant is the single place the key set for each group is
     * declared — the constructor builds $this->fields_options from it, and
     * brandFieldGroups() (a plain static read, no controller instantiation
     * required) exposes it for the story 1.18 Task 6 regression test.
     *
     * Group -> vendor controller mirrored:
     *   contact     -> hotelreservationsystem/AdminHotelGeneralSettingsController ('websitedetail')
     *   about       -> wkabouthotelblock/AdminAboutHotelBlockSettingController ('global')
     *   features    -> wkhotelfeaturesblock/AdminFeaturesModuleSettingController ('global')
     *   testimonial -> wktestimonialblock/AdminTestimonialsModuleSettingController ('modulesetting')
     */
    /**
     * The About Us page's copy — story 1.14.
     *
     * Deliberately a separate constant from VENDOR_FIELD_SPECS: those groups
     * each mirror one vendor module's own fields_options definition
     * field-for-field, so a story 1.18-style contract test can pin them to
     * a real vendor screen. These GFBRAND_ABOUTUS_* keys have no vendor
     * screen to mirror — the old WordPress About Us page has no QloApps
     * equivalent — so gfbrand.php's own installAboutUsCopyDefaults() is
     * both where they are declared AND where their defaults are seeded.
     */
    const ABOUTUS_FIELD_SPECS = [
        'GFBRAND_ABOUTUS_WHOWEARE_EYEBROW' => ['type' => 'textLang', 'lang' => true, 'required' => true, 'validation' => 'isGenericName'],
        'GFBRAND_ABOUTUS_WHOWEARE_HEADING' => ['type' => 'textLang', 'lang' => true, 'required' => true, 'validation' => 'isGenericName'],
        'GFBRAND_ABOUTUS_WHOWEARE_BODY' => ['type' => 'textareaLang', 'lang' => true, 'required' => true, 'validation' => 'isCleanHtml', 'rows' => '8', 'cols' => '2'],
        'GFBRAND_ABOUTUS_MISSION_HEADING' => ['type' => 'textareaLang', 'lang' => true, 'required' => true, 'validation' => 'isCleanHtml', 'rows' => '4', 'cols' => '2'],
        'GFBRAND_ABOUTUS_OFFER_HEADING' => ['type' => 'textLang', 'lang' => true, 'required' => true, 'validation' => 'isGenericName'],
        'GFBRAND_ABOUTUS_CTA_HEADING' => ['type' => 'textareaLang', 'lang' => true, 'required' => true, 'validation' => 'isCleanHtml', 'rows' => '3', 'cols' => '2'],
        'GFBRAND_ABOUTUS_CTA_SUBTEXT' => ['type' => 'textLang', 'lang' => true, 'validation' => 'isGenericName'],
    ];

    const VENDOR_FIELD_SPECS = [
        'contact' => [
            'PS_SHOP_NAME' => ['type' => 'text', 'validation' => 'isGenericName', 'required' => true, 'no_escape' => true],
            'PS_SHOP_EMAIL' => ['type' => 'text', 'validation' => 'isEmail', 'required' => true],
            'PS_SHOP_PHONE' => ['type' => 'text', 'validation' => 'isGenericName', 'required' => true],
            'PS_SHOP_ADDR1' => ['type' => 'text', 'validation' => 'isAddress', 'isCleanHtml' => true, 'required' => true],
            'PS_SHOP_ADDR2' => ['type' => 'text', 'validation' => 'isAddress'],
        ],
        'about' => [
            'HOTEL_INTERIOR_HEADING' => ['type' => 'textLang', 'lang' => true, 'required' => true, 'validation' => 'isGenericName'],
            'HOTEL_INTERIOR_DESCRIPTION' => ['type' => 'textareaLang', 'lang' => true, 'required' => true, 'validation' => 'isGenericName', 'rows' => '4', 'cols' => '2'],
        ],
        'features' => [
            'HOTEL_AMENITIES_HEADING' => ['type' => 'textLang', 'lang' => true, 'required' => true, 'validation' => 'isGenericName'],
            'HOTEL_AMENITIES_DESCRIPTION' => ['type' => 'textareaLang', 'lang' => true, 'required' => true, 'validation' => 'isGenericName', 'rows' => '4', 'cols' => '2'],
        ],
        'testimonial' => [
            'HOTEL_TESIMONIAL_BLOCK_HEADING' => ['type' => 'textLang', 'lang' => true, 'required' => true, 'validation' => 'isGenericName'],
            'HOTEL_TESIMONIAL_BLOCK_CONTENT' => ['type' => 'textareaLang', 'lang' => true, 'required' => true, 'validation' => 'isGenericName', 'rows' => '4', 'cols' => '2'],
        ],
    ];

    /**
     * @var GFEstablishmentRepository
     */
    private $repository;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'configuration';
        $this->className = 'Configuration';

        $this->fields_options = [
            'contact' => $this->fieldsOptionsFor('contact', $this->l('Shop Contact Details'), 'icon-envelope', [
                'PS_SHOP_NAME' => [$this->l('Website name'), $this->l('Displayed in emails and page titles.')],
                'PS_SHOP_EMAIL' => [$this->l('Website email'), $this->l('Displayed in emails sent to customers and on the Contact Us page.')],
                'PS_SHOP_PHONE' => [$this->l('Phone'), $this->l('The phone number of the main branch.')],
                'PS_SHOP_ADDR1' => [$this->l('Address line 1'), $this->l('The address of the main branch.')],
                'PS_SHOP_ADDR2' => [$this->l('Address line 2'), null],
            ]),
            'about' => $this->fieldsOptionsFor('about', $this->l('About Block'), 'icon-info-circle', [
                'HOTEL_INTERIOR_HEADING' => [$this->l('About block title'), $this->l('Enter a title for the About block.')],
                'HOTEL_INTERIOR_DESCRIPTION' => [$this->l('About block description'), $this->l('Enter a description for the About block.')],
            ]),
            'features' => $this->fieldsOptionsFor('features', $this->l('Amenities Block'), 'icon-check-square-o', [
                'HOTEL_AMENITIES_HEADING' => [$this->l('Amenities block title'), $this->l('Enter a title for the amenities block.')],
                'HOTEL_AMENITIES_DESCRIPTION' => [$this->l('Amenities block description'), $this->l('Enter a description for the amenities block.')],
            ]),
            /*
             * Heading/description only — individual testimonial rows stay on
             * wktestimonialblock's own screen; see story 1.18's Task 4 for
             * why this tab deliberately does not manage them.
             */
            'testimonial' => $this->fieldsOptionsFor('testimonial', $this->l('Testimonials Block'), 'icon-quote-right', [
                'HOTEL_TESIMONIAL_BLOCK_HEADING' => [$this->l('Testimonials block title'), $this->l('Testimonials block title, e.g. "Guest testimonials".')],
                'HOTEL_TESIMONIAL_BLOCK_CONTENT' => [$this->l('Testimonials block description'), $this->l('Testimonials block description.')],
            ]),
            /*
             * Story 1.14: the About Us page's five bands. Only each band's
             * main heading/body is editable here — the repeating items
             * within a band (mission pillars, offer cards, the Why Choose
             * Us checklist) are fixed strings in aboutus.php/gfbrand.php,
             * the same choice already made for the homepage's own pillar
             * row and checklist.
             */
            'aboutus' => $this->fieldsOptionsForAboutUs(),
        ];

        parent::__construct();
    }

    /**
     * Layer translated title/hint strings onto one group of
     * self::VENDOR_FIELD_SPECS, producing what PrestaShop's HelperOptions
     * expects in $this->fields_options.
     *
     * @param  string  $group  key into self::VENDOR_FIELD_SPECS
     * @param  string  $title
     * @param  string  $icon
     * @param  array<string, array{0: string, 1: ?string}> $labels field key => [title, hint]
     * @return array
     */
    private function fieldsOptionsFor($group, $title, $icon, array $labels)
    {
        $fields = [];

        foreach (self::VENDOR_FIELD_SPECS[$group] as $key => $spec) {
            list($fieldTitle, $hint) = $labels[$key];
            $fields[$key] = array_merge($spec, ['title' => $fieldTitle]);

            if ($hint !== null) {
                $fields[$key]['hint'] = $hint;
            }
        }

        return [
            'title' => $title,
            'icon' => $icon,
            'fields' => $fields,
            'submit' => ['title' => $this->l('Save')],
        ];
    }

    /**
     * The "About Us Page" fields_options group — story 1.14.
     *
     * Mirrors fieldsOptionsFor()'s shape but reads ABOUTUS_FIELD_SPECS
     * directly (a flat key => spec map, not VENDOR_FIELD_SPECS's
     * group => key => spec nesting) since there is only one group here and
     * no vendor screen's own grouping to follow.
     *
     * @return array
     */
    private function fieldsOptionsForAboutUs()
    {
        $labels = [
            'GFBRAND_ABOUTUS_WHOWEARE_EYEBROW' => [$this->l('Who We Are: eyebrow'), $this->l('Small label above the headline, e.g. "Who We Are".')],
            'GFBRAND_ABOUTUS_WHOWEARE_HEADING' => [$this->l('Who We Are: headline'), $this->l('The band\'s main headline.')],
            'GFBRAND_ABOUTUS_WHOWEARE_BODY' => [$this->l('Who We Are: body copy'), $this->l('Separate paragraphs with a blank line. The last paragraph renders in bold as the closing line.')],
            'GFBRAND_ABOUTUS_MISSION_HEADING' => [$this->l('Our Mission: statement'), $this->l('The centred mission statement.')],
            'GFBRAND_ABOUTUS_OFFER_HEADING' => [$this->l('What We Offer: headline'), $this->l('Heading above the three offer cards.')],
            'GFBRAND_ABOUTUS_CTA_HEADING' => [$this->l('Closing CTA: headline'), $this->l('The full-width closing band\'s headline.')],
            // Deliberately optional, unlike every other field in this
            // group: the closing band reads fine with just its headline.
            'GFBRAND_ABOUTUS_CTA_SUBTEXT' => [$this->l('Closing CTA: supporting line'), $this->l('Optional. A short line under the headline.')],
        ];

        $fields = [];

        foreach (self::ABOUTUS_FIELD_SPECS as $key => $spec) {
            list($fieldTitle, $hint) = $labels[$key];
            $fields[$key] = array_merge($spec, ['title' => $fieldTitle]);

            if ($hint !== null) {
                $fields[$key]['hint'] = $hint;
            }
        }

        return [
            'title' => $this->l('About Us Page'),
            'icon' => 'icon-file-text',
            'fields' => $fields,
            'submit' => ['title' => $this->l('Save')],
        ];
    }

    /**
     * The vendor-key contract, one group at a time — no controller
     * instantiation required, so the story 1.18 Task 6 regression test can
     * call this directly without standing up a full admin request context.
     *
     * @return array<string, string[]> group name => config key names
     */
    public static function brandFieldGroups()
    {
        return array_map('array_keys', self::VENDOR_FIELD_SPECS);
    }

    /**
     * Append the featured-establishments link panel after the standard
     * options groups render — see the class docblock for why this links out
     * rather than re-rendering the picker itself.
     */
    public function initContent()
    {
        parent::initContent();

        $this->content .= $this->renderFeaturedEstablishmentsPanel();
        $this->context->smarty->assign('content', $this->content);
    }

    private function renderFeaturedEstablishmentsPanel()
    {
        $rows = $this->repository()->isSchemaReady()
            ? $this->repository()->findAllForFeaturedPicker((int) $this->context->language->id)
            : [];

        $featuredCount = count(array_filter($rows, function ($row) {
            return (int) $row['gf_featured_home'] === 1;
        }));

        $tpl = $this->context->smarty->createTemplate(
            $this->module->getLocalPath() . 'views/templates/admin/brand_settings_featured_link.tpl',
            $this->context->smarty
        );

        $tpl->assign([
            'gf_featured_count' => $featuredCount,
            'gf_establishments_count' => count($rows),
            'gf_establishments_admin_url' => $this->context->link->getAdminLink('AdminGfEstablishments'),
        ]);

        return $tpl->fetch();
    }

    /**
     * @return GFEstablishmentRepository
     */
    private function repository()
    {
        if (!$this->repository) {
            require_once dirname(__FILE__) . '/../../lib/Repository/GFEstablishmentRepository.php';
            $this->repository = new GFEstablishmentRepository();
        }

        return $this->repository;
    }
}
