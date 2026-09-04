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
    /** Cards per page (AC-7). */
    const PER_PAGE = 12;

    /** The establishment has its own site: send the visitor there. */
    const CTA_EXTERNAL = 'external';

    /** No site of its own: keep the visitor here and let them enquire. */
    const CTA_INQUIRE = 'inquire';

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
     * Turn a database row into what the grid renders.
     *
     * Story 1.10 owns the card component and the full conditional-CTA decision
     * table (D11/D12). This produces only what the grid needs to be coherent
     * today, and names the CTA by intent rather than by label so 1.10 can
     * change the wording without touching this.
     *
     * @param  array $row
     * @return array
     */
    private function toCard(array $row)
    {
        $destination = isset($row['gf_destination_url']) ? trim((string) $row['gf_destination_url']) : '';
        $type = isset($row['gf_type']) ? (string) $row['gf_type'] : '';

        return [
            'id_product' => (int) $row['id_product'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description_short'],
            'link_rewrite' => isset($row['link_rewrite']) ? (string) $row['link_rewrite'] : '',
            'id_image' => isset($row['id_image']) ? (int) $row['id_image'] : 0,
            'type' => $type,
            'type_label' => self::labelForType($type),
            'city' => isset($row['gf_city']) ? (string) $row['gf_city'] : '',
            'country' => isset($row['gf_country']) ? (string) $row['gf_country'] : '',
            'certification' => isset($row['gf_certification']) ? (string) $row['gf_certification'] : '',
            'cta' => $destination === '' ? self::CTA_INQUIRE : self::CTA_EXTERNAL,
            // Empty for CTA_INQUIRE: the enquiry URL is an internal route, and
            // routing is the front controller's job, not this layer's.
            'cta_url' => $destination,
        ];
    }
}
