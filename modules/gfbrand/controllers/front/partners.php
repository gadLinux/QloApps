<?php
/**
 * 2026 GF Experiences
 *
 * The standalone /partners/ page — story 1.11.
 *
 * A self-contained controller for the partner section: it resolves the shared
 * advisor/partner data, picks the partners template, and renders.
 *
 * Why not just extend the advisors controller? The Dispatcher includes only
 * the one controller file it resolves from the route, so a parent class in a
 * sibling file would never be loaded — the partners page would fatal. The
 * two pages share the data service (GFAdvisorPartnerListing) and the section
 * component (_advisor-partner-section.tpl), which is where the commonality
 * actually lives; the controllers themselves are thin.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class GfbrandPartnersModuleFrontController extends ModuleFrontController
{
    /** @var bool Public; no customer context needed. */
    public $auth = false;

    /** @var bool */
    public $ssl = true;

    /*
     * php_self is deliberately NOT set — the same reason as the establishments
     * controller: a canonical redirect built by Link::getPageLink() cannot
     * resolve a module route and would 302 the page out of its own friendly
     * URL.
     */

    public function initContent()
    {
        // The drawer this page mirrors is itself suppressed when the brand
        // module is switched off; this standalone route must not stay public
        // and indexable while that is true.
        if (!Configuration::get(Gfbrand::CONFIG_PREFIX . 'ENABLED')) {
            Tools::redirect('index.php?controller=404');

            return;
        }

        parent::initContent();

        $listing = $this->module->getAdvisorPartnerListing();

        $this->context->smarty->assign([
            'gf_advisors' => $listing->advisorCards(),
            'gf_partners' => $listing->partnerCards(),
            'gf_has_advisors' => $listing->hasAdvisors(),
            'gf_has_partners' => $listing->hasPartners(),
            'gf_section' => 'partners',
            // The page already renders this exact copy in its own <h1>; the
            // shared component's <h2> would only duplicate it.
            'gf_show_section_heading' => false,
            'gf_section_template' => _PS_MODULE_DIR_
                . 'gfbrand/views/templates/front/_advisor-partner-section.tpl',
            'gf_advisor_image_base' => __PS_BASE_URI__ . 'uploads/establishments/advisors/',
            'gf_partner_image_base' => __PS_BASE_URI__ . 'uploads/establishments/partners/',
            'gf_page_title' => $this->module->l('Our Partners'),
            'gf_page_intro' => $this->module->l('The organisations that vouch for our gluten-free standards.'),
        ]);

        $this->setTemplate('partners.tpl');
    }
}
