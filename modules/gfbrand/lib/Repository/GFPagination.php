<?php
/**
 * 2026 GF Experiences
 *
 * Paging arithmetic for a filtered listing — story 1.9 AC-7.
 *
 * A value object rather than four variables in a controller: the page number
 * arrives from the query string, so it is attacker-controlled and bookmark-
 * stale, and every clamp it needs is the difference between a coherent last
 * page and a negative SQL offset.
 *
 * DOMAIN LAYER — no SQL, no output, no request handling.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFPagination
{
    /** @var int Rows in the whole filtered set. */
    private $total;

    /** @var int */
    private $perPage;

    /** @var int Clamped into [1, pageCount]. */
    private $page;

    /**
     * @param int $total   Rows matching the current filter.
     * @param int $perPage Page size; forced to at least 1.
     * @param mixed $requestedPage Raw ?page= value — any type, any value.
     */
    public function __construct($total, $perPage, $requestedPage)
    {
        $this->total = max(0, (int) $total);
        $this->perPage = max(1, (int) $perPage);
        $this->page = $this->clamp($requestedPage);
    }

    /**
     * @return int Always within [1, getPageCount()].
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @return int At least 1, so "page 1 of 1" reads correctly with no results.
     */
    public function getPageCount()
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /**
     * @return int Rows to skip. Never negative.
     */
    public function getOffset()
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function getLimit()
    {
        return $this->perPage;
    }

    /**
     * The whole filtered set, not the current page — this is the number the
     * live region announces (AC-5).
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * False when everything fits on one page, so the template can leave the
     * pager out entirely rather than render a single dead "1".
     */
    public function isNeeded()
    {
        return $this->getPageCount() > 1;
    }

    public function hasPrevious()
    {
        return $this->page > 1;
    }

    public function hasNext()
    {
        return $this->page < $this->getPageCount();
    }

    /**
     * @return int[] Every page number, for the pager.
     */
    public function getPages()
    {
        return range(1, $this->getPageCount());
    }

    /**
     * A bookmark that outlived the data lands on the last page; a crawler's
     * ?page=0 or ?page=-1 lands on the first.
     *
     * @param  mixed $requested
     * @return int
     */
    private function clamp($requested)
    {
        $page = is_numeric($requested) ? (int) $requested : 1;

        return max(1, min($page, $this->getPageCount()));
    }
}
