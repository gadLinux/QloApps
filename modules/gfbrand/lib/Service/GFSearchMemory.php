<?php
/**
 * 2026 GF Experiences
 *
 * Decides when to remember a search and when to put it back.
 *
 * Both halves hang off one set of panel parameters, which is why they live
 * together: if the panel was built from a real search, that is what to
 * remember; if it was built from nothing, that is where the remembered one
 * goes. Restoring only ever fills gaps — the page being looked at always wins,
 * or a customer on one hotel's page would be shown another's.
 *
 * Pure array in, array out, so the policy is testable without a shop.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFSearchMemory
{
    /** @var GFSearchPreferenceRepository */
    private $repository;

    public function __construct(GFSearchPreferenceRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Remember this page's search, or fill the panel with the last one.
     *
     * @param  array  $panelParams The search block's Smarty variables.
     * @param  string $today       Y-m-d, injected so the policy stays testable.
     * @return array  The parameters to render with.
     */
    public function apply(array $panelParams, $today)
    {
        $searchData = isset($panelParams['search_data']) ? $panelParams['search_data'] : [];

        if ($this->isRealSearch($searchData)) {
            $this->repository->save($this->toPreference($searchData));

            return $panelParams;
        }

        $preference = $this->repository->find();

        if ($preference === null) {
            return $panelParams;
        }

        if ($preference->datesAreStale($today)) {
            // Pre-filling a check-in that has passed offers a search the shop
            // then rejects, which is worse than an empty field.
            $preference = $preference->withoutDates();
        }

        $panelParams['search_data'] = $this->fillGaps($searchData, $preference);

        return $panelParams;
    }

    /**
     * True when this page was built from a search the customer actually made.
     *
     * The panel defaults the dates to today and tomorrow on any hotel page,
     * search or not, and only carries date_from when the request asked for it —
     * so their presence is what separates a search from a browse.
     *
     * @return bool
     */
    private function isRealSearch(array $searchData)
    {
        return !empty($searchData['date_from']) && !empty($searchData['date_to']);
    }

    /**
     * @return GFSearchPreference
     */
    private function toPreference(array $searchData)
    {
        $preference = new GFSearchPreference();

        $preference->hotelId = $this->hotelField($searchData, 'id');
        $preference->hotelCategoryId = $this->hotelField($searchData, 'id_category');
        $preference->locationCategoryId = isset($searchData['location_category_id'])
            ? (int) $searchData['location_category_id']
            : 0;
        $preference->locationName = isset($searchData['location']) ? (string) $searchData['location'] : '';
        $preference->dateFrom = (string) $searchData['date_from'];
        $preference->dateTo = (string) $searchData['date_to'];
        $preference->occupancies = isset($searchData['occupancies']) && is_array($searchData['occupancies'])
            ? $searchData['occupancies']
            : [];

        return $preference;
    }

    /**
     * Add what the page does not already know, and nothing else.
     *
     * @return array
     */
    private function fillGaps(array $searchData, GFSearchPreference $preference)
    {
        if (!isset($searchData['htl_dtl']) && ($preference->hotelId || $preference->hotelCategoryId)) {
            $searchData['htl_dtl'] = [
                'id' => $preference->hotelId,
                'id_category' => $preference->hotelCategoryId,
                // The room-type page's template reads both of these unguarded.
                'hotel_name' => '',
                'city' => '',
            ];
        }

        if ($preference->hasDates() && !isset($searchData['date_from'])) {
            $searchData['date_from'] = $preference->dateFrom;
            $searchData['date_to'] = $preference->dateTo;
        }

        if ($preference->locationName !== '' && !isset($searchData['location'])) {
            $searchData['location'] = $preference->locationName;
            $searchData['location_category_id'] = $preference->locationCategoryId;
        }

        if (!empty($preference->occupancies) && !isset($searchData['occupancies'])) {
            $searchData['occupancies'] = $preference->occupancies;
            $searchData['occupancy_adults'] = $preference->countAdults();
            $searchData['occupancy_children'] = $preference->countChildren();
        }

        return $this->withTemplateDefaults($searchData);
    }

    /**
     * Keys the stock room-type template reads without checking they are set.
     *
     * @return array
     */
    private function withTemplateDefaults(array $searchData)
    {
        if (!isset($searchData['order_date_restrict'])) {
            $searchData['order_date_restrict'] = false;
        }

        if (!isset($searchData['num_days'])) {
            $searchData['num_days'] = $this->nightsBetween($searchData);
        }

        return $searchData;
    }

    /**
     * @return int
     */
    private function nightsBetween(array $searchData)
    {
        if (empty($searchData['date_from']) || empty($searchData['date_to'])) {
            return 0;
        }

        $nights = (strtotime($searchData['date_to']) - strtotime($searchData['date_from'])) / 86400;

        return $nights > 0 ? (int) $nights : 0;
    }

    /**
     * @return int
     */
    private function hotelField(array $searchData, $field)
    {
        return isset($searchData['htl_dtl'][$field]) ? (int) $searchData['htl_dtl'][$field] : 0;
    }
}
