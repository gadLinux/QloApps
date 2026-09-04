<?php
/**
 * 2026 GF Experiences
 *
 * The GF Establishments listing — story 1.9, FR-18/FR-19.
 *
 * WHY A MODULE FRONT CONTROLLER (TQ-3, D7)
 *
 * TQ-3 asked whether wkhotelfilterblock could carry a country facet without an
 * override/. It cannot: its facets are built from product features and
 * attributes of BOOKABLE room types, and a country lives on the establishment
 * product instead. Extending it would have meant an override — layer 5, and a
 * permanent upgrade cost — to add a facet to a block this page does not
 * otherwise use. This listing is not the room-search results page; it is a
 * marketing listing with its own query. A module front controller is layer 1
 * and owes the stock modules nothing.
 *
 * PRESENTATION LAYER — it resolves the request, asks the listing service for a
 * result, and decorates it with URLs. It holds no SQL and no filter policy.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class GfbrandEstablishmentsModuleFrontController extends ModuleFrontController
{
    /** Query parameter carrying the country. Fixed by the live site's URLs. */
    const COUNTRY_PARAM = 'country';

    /** Query parameter carrying the page number. */
    const PAGE_PARAM = 'page';

    /** Card images: 720x720, so a ~380px card still looks right on a 2x screen. */
    const IMAGE_TYPE = 'large_default';

    /** @var bool The listing is public; no customer context is needed. */
    public $auth = false;

    /** @var bool */
    public $ssl = true;

    /*
     * php_self is deliberately NOT set.
     *
     * FrontController::init() canonical-redirects whenever php_self is
     * non-empty, and it builds the canonical with Link::getPageLink(), which
     * only knows core pages. For a module controller that resolves to
     * index.php?controller=establishments — so /establishments would 302
     * straight back out of its own friendly URL, undoing AC-8.
     *
     * With it unset, PrestaShop still derives the page name from the route
     * (module-gfbrand-establishments), which is what the body class uses.
     */

    public function setMedia()
    {
        parent::setMedia();

        // The brand stylesheet is registered globally by the module's
        // actionFrontControllerSetMedia hook, so nothing to add here. The
        // filter needs no JavaScript at all: see initContent().
    }

    public function initContent()
    {
        parent::initContent();

        $result = $this->listing()->forRequest(
            Tools::getValue(self::COUNTRY_PARAM),
            Tools::getValue(self::PAGE_PARAM, 1),
            (int) $this->context->language->id
        );

        $filter = $result->getFilter();

        $this->context->smarty->assign([
            'gf_pills' => $this->decoratePills($filter),
            'gf_establishments' => $this->decorateCards($result->getEstablishments()),
            'gf_selected_country' => $filter->getSelected(),
            'gf_is_showing_all' => $filter->isShowingAll(),
            'gf_total' => $result->getTotal(),
            'gf_is_empty' => $result->isEmpty(),
            'gf_pager' => $this->decoratePager($result),
            'gf_show_all_url' => $this->urlFor('', 1),
            'gf_heading' => $this->heading($filter),
            'gf_tagline' => Configuration::get('GFBRAND_TAGLINE'),
        ]);

        $this->setTemplate('establishments.tpl');
    }

    /**
     * Canonical and paged URLs both come from here, so the ?country= shape the
     * live site already publishes survives every link on the page.
     *
     * @param  string $country Canonical country, '' for all.
     * @param  int    $page
     * @return string
     */
    private function urlFor($country, $page = 1)
    {
        $params = [];

        if ($country !== '') {
            $params[self::COUNTRY_PARAM] = $country;
        }

        // Page 1 is the bare URL: a filter pill should not publish ?page=1,
        // and neither should the canonical link.
        if ((int) $page > 1) {
            $params[self::PAGE_PARAM] = (int) $page;
        }

        return $this->context->link->getModuleLink('gfbrand', 'establishments', $params);
    }

    /**
     * @return array[] Pills with the URL each one navigates to.
     */
    private function decoratePills(GFCountryFilter $filter)
    {
        $pills = [];

        foreach ($filter->getPills() as $pill) {
            // Changing the filter always returns to page 1: page 3 of Canada
            // is rarely page 3 of Spain, and landing on an empty page reads
            // as a broken filter.
            $pill['url'] = $this->urlFor($pill['value'], 1);
            $pills[] = $pill;
        }

        return $pills;
    }

    /**
     * Add the URLs the cards need. The listing service deliberately produces
     * none: building them needs Link and Image, which are framework concerns.
     *
     * @param  array[] $cards
     * @return array[]
     */
    private function decorateCards(array $cards)
    {
        foreach ($cards as $index => $card) {
            $cards[$index]['image_url'] = $this->imageUrl($card);
            $cards[$index]['url'] = $this->context->link->getProductLink(
                $card['id_product'],
                $card['link_rewrite']
            );

            // Story 1.10 owns the conditional CTA proper. Until then an
            // establishment with no site of its own points at its own page
            // rather than at an empty href.
            if ($card['cta'] === GFEstablishmentListing::CTA_INQUIRE) {
                $cards[$index]['cta_url'] = $cards[$index]['url'];
            }
        }

        return $cards;
    }

    /**
     * @return string Empty when the establishment has no image at all, which
     *                the template renders as a branded blank rather than a
     *                broken-image icon.
     */
    private function imageUrl(array $card)
    {
        if ($card['id_image'] === 0) {
            return '';
        }

        return $this->context->link->getImageLink(
            $card['link_rewrite'],
            $card['id_image'],
            self::IMAGE_TYPE
        );
    }

    /**
     * @return array Pager description, or [] when everything fits on one page.
     */
    private function decoratePager(GFEstablishmentListingResult $result)
    {
        $pagination = $result->getPagination();

        if (!$pagination->isNeeded()) {
            return [];
        }

        $country = $result->getFilter()->getSelected();
        $pages = [];

        foreach ($pagination->getPages() as $number) {
            $pages[] = [
                'number' => $number,
                'url' => $this->urlFor($country, $number),
                'current' => $number === $pagination->getPage(),
            ];
        }

        return [
            'pages' => $pages,
            'current' => $pagination->getPage(),
            'count' => $pagination->getPageCount(),
            // AC-7: the country rides along, so paging never silently widens
            // the listing back to everything.
            'previous_url' => $pagination->hasPrevious()
                ? $this->urlFor($country, $pagination->getPage() - 1)
                : '',
            'next_url' => $pagination->hasNext()
                ? $this->urlFor($country, $pagination->getPage() + 1)
                : '',
        ];
    }

    /**
     * @return string The h1, which names the filter when one is applied so the
     *                page title matches what is on screen.
     */
    private function heading(GFCountryFilter $filter)
    {
        if ($filter->isShowingAll()) {
            return $this->module->l('Establishments around the world', 'establishments');
        }

        return sprintf(
            $this->module->l('Establishments in %s', 'establishments'),
            $filter->getSelected()
        );
    }

    /**
     * @return GFEstablishmentListing
     */
    private function listing()
    {
        return $this->module->getEstablishmentListing();
    }
}
