<?php
/**
 * 2026 GF Experiences
 *
 * One rendering of the establishments listing — story 1.9.
 *
 * The contract between the listing service and everything that displays it:
 * the front controller, the template, and the tests. Grouping the three parts
 * keeps them consistent — the count in the live region, the pills and the
 * cards are all of the same filtered set, which is exactly what goes wrong
 * when a controller assembles them from separate calls.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFEstablishmentListingResult
{
    /** @var GFCountryFilter */
    private $filter;

    /** @var GFPagination */
    private $pagination;

    /** @var array[] Cards on the current page. */
    private $establishments;

    public function __construct(GFCountryFilter $filter, GFPagination $pagination, array $establishments)
    {
        $this->filter = $filter;
        $this->pagination = $pagination;
        $this->establishments = $establishments;
    }

    /**
     * @return GFCountryFilter
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @return GFPagination
     */
    public function getPagination()
    {
        return $this->pagination;
    }

    /**
     * @return array[] One entry per card on this page.
     */
    public function getEstablishments()
    {
        return $this->establishments;
    }

    /**
     * Establishments matching the filter, across every page. This is the
     * number announced in the live region (AC-5), not the page's card count.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->pagination->getTotal();
    }

    /**
     * Nothing matched — the template shows the empty state (AC-6) rather than
     * a blank grid.
     */
    public function isEmpty()
    {
        return $this->getTotal() === 0;
    }
}
