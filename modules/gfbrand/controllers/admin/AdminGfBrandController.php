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
