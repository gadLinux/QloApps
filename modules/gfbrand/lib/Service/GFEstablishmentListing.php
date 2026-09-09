<?php
/**
 * 2026 GF Experiences
 *
 * Assembles the establishments listing page — story 1.9, FR-18/FR-19.
 *
 * Everything here happens on the server. D6 requires the country filter to
 * work with JavaScript disabled, so the filter is a query parameter that
 * reaches SQL, not a class toggled on cards the browser already has. Any
 * client-side transition is layered on top of a page that is already correct.
 *
 * APPLICATION LAYER — orchestrates the repository and the two domain value
 * objects. It holds no SQL and builds no URLs; URLs need the PrestaShop Link
 * service and belong to the front controller.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFEstablishmentListing
{
    /** Cards per page (story 1.9 AC-7). */
    const PER_PAGE = 12;

    /** @var GFEstablishmentRepository */
    private $repository;

    /** @var int */
    private $perPage;

    public function __construct(GFEstablishmentRepository $repository, $perPage = self::PER_PAGE)
    {
        $this->repository = $repository;
        $this->perPage = (int) $perPage;
    }

    /**
     * Build one rendering of the listing.
     *
     * @param  string|null $country Raw ?country= value.
     * @param  mixed       $page    Raw ?page= value.
     * @param  int         $idLang
     * @return GFEstablishmentListingResult
     */
    public function forRequest($country, $page, $idLang)
    {
        $filter = new GFCountryFilter($this->repository->findCountries(), $country);
        $selected = $filter->getSelected();

        $pagination = new GFPagination(
            $this->repository->countListing($selected),
            $this->perPage,
            $page
        );

        return new GFEstablishmentListingResult(
            $filter,
            $pagination,
            $this->cards($selected, $pagination, $idLang)
        );
    }

    /**
     * A human label for the type pill above the name.
     *
     * The enum shouts (HOTEL, RESTAURANT, EXPERIENCE) because it is a storage
     * value; the pill is uppercased by CSS instead, so screen readers are not
     * handed an acronym-shaped word to spell out.
     *
     * @param  string $type
     * @return string
     */
    public static function labelForType($type)
    {
        return Tools::ucfirst(Tools::strtolower((string) $type));
    }

    /**
     * @return array[]
     */
    private function cards($country, GFPagination $pagination, $idLang)
    {
        if ($pagination->getTotal() === 0) {
            return [];
        }

        $rows = $this->repository->findListing(
            $country,
            $pagination->getLimit(),
            $pagination->getOffset(),
            (int) $idLang
        );

        return array_map([$this, 'toCard'], $rows);
    }

    /**
     * A human label for a certification level.
     *
     * "GF Dedicated" (a dedicated gluten-free kitchen) and "GF Options" (a
     * kitchen that can accommodate) mean materially different things to a
     * coeliac traveller, so they are never collapsed into one word. An
     * establishment we have no answer for gets no pill at all rather than a
     * reassuring-looking default (D11).
     *
     * @param  string $certification
     * @return string Empty when unknown.
     */
    public static function labelForCertification($certification)
    {
        switch ((string) $certification) {
            case GFEstablishment::CERTIFICATION_DEDICATED:
                return 'GF Dedicated';
            case GFEstablishment::CERTIFICATION_OPTIONS:
                return 'GF Options';
            default:
                return '';
        }
    }

    /**
     * Turn a database row into what a card renders.
     *
     * The CTA is decided here, in one place, by GFEstablishmentCta — the card
     * template only renders the answer. That is what makes AC-4 true: an admin
     * flipping the channel-manager flag changes the button, because the branch
     * is a function of the row and nothing else.
     *
     * @param  array $row
     * @return array
     */
    protected function toCard(array $row)
    {
        $type = isset($row['gf_type']) ? (string) $row['gf_type'] : '';
        $certification = isset($row['gf_certification']) ? (string) $row['gf_certification'] : '';

        $cta = new GFEstablishmentCta(
            $type,
            isset($row['gf_destination_url']) ? $row['gf_destination_url'] : '',
            isset($row['gf_has_channel_manager']) ? $row['gf_has_channel_manager'] : false,
            isset($row['gf_channel_manager_status']) ? $row['gf_channel_manager_status'] : ''
        );

        return [
            'id_product' => (int) $row['id_product'],
            'source_id' => isset($row['gf_source_id']) ? (string) $row['gf_source_id'] : '',
            'name' => (string) $row['name'],
            'description' => (string) $row['description_short'],
            'link_rewrite' => isset($row['link_rewrite']) ? (string) $row['link_rewrite'] : '',
            'id_image' => isset($row['id_image']) ? (int) $row['id_image'] : 0,
            'type' => $type,
            'type_label' => self::labelForType($type),
            'city' => isset($row['gf_city']) ? (string) $row['gf_city'] : '',
            'country' => isset($row['gf_country']) ? (string) $row['gf_country'] : '',
            'certification' => $certification,
            'certification_label' => self::labelForCertification($certification),
            'cta' => $cta->getKind(),
            'cta_label' => $cta->getLabel(),
            'cta_present' => $cta->isPresent(),
            'cta_new_tab' => $cta->opensInNewTab(),
            // Only the off-site URL is known here. The enquiry and booking
            // targets are internal routes, and routing is the front
            // controller's job, not this layer's.
            'cta_url' => $cta->getExternalUrl(),
        ];
    }

    /**
     * Exposed so other pages (the homepage strip, story 1.13) build the exact
     * same card shape rather than forking the logic.
     *
     * @param  array $row
     * @return array
     */
    public function toCardForTest(array $row)
    {
        return $this->toCard($row);
    }
}
