<?php
/**
 * 2026 GF Experiences
 *
 * Applies the remembered search to the booking search panel.
 *
 * Sits on the stock module's actionSearchPanelParamsModifier hook, so nothing
 * in wkroomsearchblock is edited and an upgrade of it cannot lose this. Its
 * job is the shop-facing half the policy deliberately does not do: today's
 * date, and the booking window of whichever hotel was restored.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFSearchPanel
{
    /** @var GFSearchMemory */
    private $memory;

    public function __construct(GFSearchMemory $memory)
    {
        $this->memory = $memory;
    }

    /**
     * @param  array $panelParams The search block's Smarty variables.
     * @return array
     */
    public function decorate(array $panelParams)
    {
        $decorated = $this->memory->apply($panelParams, date('Y-m-d'));

        return $this->applyBookingWindow($decorated);
    }

    /**
     * Re-read the booking window for the restored hotel.
     *
     * The panel computes these for "no hotel chosen", and the stock JavaScript
     * only refreshes them when the customer picks one from the dropdown. A
     * hotel we restored server-side was never picked, so the date picker would
     * otherwise be bounded by the shop-wide defaults instead of that hotel's.
     *
     * @return array
     */
    private function applyBookingWindow(array $panelParams)
    {
        if (!class_exists('HotelOrderRestrictDate')) {
            return $panelParams;
        }

        $idHotel = isset($panelParams['search_data']['htl_dtl']['id'])
            ? (int) $panelParams['search_data']['htl_dtl']['id']
            : 0;

        if (!$idHotel) {
            return $panelParams;
        }

        $maxOrderDate = HotelOrderRestrictDate::getMaxOrderDate($idHotel);

        $panelParams['max_order_date'] = date('Y-m-d', strtotime($maxOrderDate));
        $panelParams['min_booking_offset'] = (int) HotelOrderRestrictDate::getMinimumBookingOffset($idHotel);

        return $panelParams;
    }
}
