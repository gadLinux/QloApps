<?php
/**
 * 2026 GF Experiences
 *
 * The /about-us/ page — story 1.14.
 *
 * The old WordPress site's About Us page has no QloApps equivalent: a
 * visitor deciding to trust GF Experiences with a trip could not read who
 * they are from the booking platform. This controller renders it as a
 * dedicated gfbrand front controller — the same shape as
 * establishments.php/advisors.php/partners.php — so it needs no theme
 * edits at all; ModuleFrontController already renders the active theme's
 * full header/nav/footer.
 *
 * Five fixed-structure bands (Who We Are, Our Mission, What We Offer, Why
 * Choose Us, Closing CTA). Most band copy is owner-editable via the
 * GFBRAND_ABOUTUS_* keys (see AdminGfBrandController's "aboutus" fields_
 * options group); the repeating list items within a band (mission pillars,
 * offer cards) are fixed strings, wrapped for translation only — the same
 * pattern gfbrand.php's own buildPillars()/whyChooseItems() already use for
 * the homepage.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class GfbrandAboutusModuleFrontController extends ModuleFrontController
{
    /** @var bool Public; no customer context needed. */
    public $auth = false;

    /** @var bool */
    public $ssl = true;

    /*
     * php_self is deliberately NOT set — same reason as the establishments
     * and advisors controllers: a canonical redirect built by
     * Link::getPageLink() cannot resolve a module route and would 302 the
     * page out of its own friendly URL.
     */

    public function initContent()
    {
        // This page is part of the branded storefront, exactly like the
        // advisor/partner drawers it borrows its Why Choose Us band from;
        // it must not stay public and indexable when the brand is switched
        // off (manually, or by the active theme not being GFExperiences —
        // story 1.20).
        if (!Gfbrand::isBrandActive()) {
            Tools::redirect('index.php?controller=404');

            return;
        }

        parent::initContent();

        $idLang = (int) $this->context->language->id;
        $assetUrl = __PS_BASE_URI__ . 'modules/gfbrand/assets/';

        $this->context->smarty->assign([
            'gf_page_title' => $this->module->l('About GF Experiences'),
            'gf_page_tagline' => Configuration::get(Gfbrand::CONFIG_PREFIX . 'TAGLINE', $idLang),

            /* Band 1: Who We Are */
            'gf_whoweare_eyebrow' => Configuration::get('GFBRAND_ABOUTUS_WHOWEARE_EYEBROW', $idLang),
            'gf_whoweare_heading' => Configuration::get('GFBRAND_ABOUTUS_WHOWEARE_HEADING', $idLang),
            'gf_whoweare_paragraphs' => $this->splitParagraphs(
                Configuration::get('GFBRAND_ABOUTUS_WHOWEARE_BODY', $idLang)
            ),
            'gf_whoweare_photo' => $assetUrl . 'about-who-we-are.avif',

            /* Band 2: Our Mission */
            'gf_mission_heading' => Configuration::get('GFBRAND_ABOUTUS_MISSION_HEADING', $idLang),
            'gf_mission_pillars' => $this->missionPillars(),

            /* Band 3: What We Offer */
            'gf_offer_heading' => Configuration::get('GFBRAND_ABOUTUS_OFFER_HEADING', $idLang),
            'gf_offer_cards' => $this->offerCards(),
            'gf_offer_photo' => $assetUrl . 'about-what-we-offer.avif',

            /* Band 4: Why Choose Us — reused verbatim from the homepage
             * (same image, same four-item checklist, same copy). See the
             * "Never" boundary: this story does not source new photography
             * or a second checklist for a band that already exists. */
            'gf_why_choose' => $this->module->whyChooseItems(),
            'gf_why_photo' => $assetUrl . 'why-us.avif',

            /* Band 5: Closing CTA */
            'gf_cta_heading' => Configuration::get('GFBRAND_ABOUTUS_CTA_HEADING', $idLang),
            'gf_cta_subtext' => Configuration::get('GFBRAND_ABOUTUS_CTA_SUBTEXT', $idLang),

            /* Shared icon partial (story 1.13's pillar icon glyphs, extended
             * with the mission/offer glyphs this page adds). */
            'gf_pillar_icon_file' => $this->module->getLocalPath() . 'views/templates/hook/pillar-icon.tpl',
        ]);

        $this->setTemplate('aboutus.tpl');
    }

    /**
     * Split the Who We Are body into paragraphs on a blank line, so the
     * admin field stays a single textarea (one thing to edit) while the
     * template still renders each paragraph as its own block and marks the
     * last one as the bold closing line (AC-1's "closing line in bold").
     *
     * @param  string $text
     * @return string[]
     */
    private function splitParagraphs($text)
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [];
        }

        return preg_split('/\r\n\r\n|\n\n|\r\r/', $text);
    }

    /**
     * Our Mission band's three icon-left/text-right pillars (AC-1). Fixed
     * content, not admin-editable — the same choice gfbrand.php's own
     * buildPillars()/whyChooseItems() already make for the homepage's
     * repeating list items, so this page's one-off admin group only grows
     * with each new band, not with every item inside one.
     *
     * @return array[]
     */
    private function missionPillars()
    {
        return [
            [
                'icon' => 'safe',
                'title' => $this->module->l('Safe Travel'),
                'copy' => $this->module->l('We ensure every place meets strict GF standards.'),
            ],
            [
                'icon' => 'verified',
                'title' => $this->module->l('Verified Places'),
                'copy' => $this->module->l('Only trusted hotels and restaurants make the list.'),
            ],
            [
                'icon' => 'stressfree',
                'title' => $this->module->l('Stress-Free Experience'),
                'copy' => $this->module->l('We take the guesswork out so you can relax and enjoy.'),
            ],
        ];
    }

    /**
     * What We Offer band's three cards (AC-1). Fixed content — see
     * missionPillars() above for why.
     *
     * @return array[]
     */
    private function offerCards()
    {
        return [
            [
                'icon' => 'hotel',
                'title' => $this->module->l('GF Hotels'),
                'copy' => $this->module->l('Carefully selected hotels with gluten-free options and trained staff for a safe stay.'),
            ],
            [
                'icon' => 'restaurant',
                'title' => $this->module->l('GF Restaurants'),
                'copy' => $this->module->l('Partner restaurants with verified gluten-free menus and safe preparation practices.'),
            ],
            [
                'icon' => 'experience',
                'title' => $this->module->l('GF Experiences'),
                'copy' => $this->module->l('Unique local experiences and tours crafted with your dietary needs in mind.'),
            ],
        ];
    }
}
