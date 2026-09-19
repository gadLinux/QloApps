<?php
/**
 * 2026 GF Experiences
 *
 * The standalone /advisors/ page — story 1.11.
 *
 * The homepage drawers are the primary affordance, but a section a guest can
 * only see behind a button cannot be linked, shared or indexed. This route
 * renders the very same component the drawer does, so the content is linkable
 * and the drawer is never the only door in.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class GfbrandAdvisorsModuleFrontController extends ModuleFrontController
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
        // is switched off (manually, or by the active theme not being
        // GFExperiences — story 1.20); this standalone route must not stay
        // public and indexable while that is true.
        if (!Gfbrand::isBrandActive()) {
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
            'gf_section' => 'advisors',
            // The page already renders this exact copy in its own <h1>; the
            // shared component's <h2> would only duplicate it.
            'gf_show_section_heading' => false,
            // The shared section component, included by the page templates and
            // by the homepage drawers alike.
            'gf_section_template' => _PS_MODULE_DIR_
                . 'gfbrand/views/templates/front/_advisor-partner-section.tpl',
            'gf_advisor_image_base' => __PS_BASE_URI__ . 'uploads/establishments/advisors/',
            'gf_partner_image_base' => __PS_BASE_URI__ . 'uploads/establishments/partners/',
            'gf_page_title' => $this->module->l('Meet the Advisors'),
            'gf_page_intro' => $this->module->l('The people who help you travel safely, gluten-free.'),
        ]);

        $this->setTemplate('advisors.tpl');
    }
}
