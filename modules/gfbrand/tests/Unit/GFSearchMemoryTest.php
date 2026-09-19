<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the remember/restore policy behind the search panel.
 *
 * The point of the feature is that a customer who reloads, leaves and comes
 * back, or lands on a different page does not retype what they already told
 * us. These tests describe when that is safe and when it is not.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFSearchMemory::class)]
class GFSearchMemoryTest extends TestCase
{
    const TODAY = '2026-09-04';

    /** @var GFSearchPreferenceRepository */
    private $repository;

    /** @var GFSearchMemory */
    private $memory;

    protected function setUp(): void
    {
        $this->repository = new GFSearchPreferenceRepository(new GFFakeCookie());
        $this->memory = new GFSearchMemory($this->repository);
    }

    #[Test]
    public function a_search_that_carries_dates_is_remembered(): void
    {
        $this->memory->apply($this->panelWithSearch(), self::TODAY);

        $remembered = $this->repository->find();

        $this->assertNotNull($remembered);
        $this->assertSame(21, $remembered->hotelId);
        $this->assertSame('2026-10-01', $remembered->dateFrom);
        $this->assertSame('2026-10-05', $remembered->dateTo);
        $this->assertCount(1, $remembered->occupancies);
    }

    /**
     * The panel puts today/tomorrow into search_data on any hotel page, search
     * or not. Remembering that would overwrite the customer's real choice with
     * a default they never made, so only a request that actually carried dates
     * counts as a search.
     */
    #[Test]
    public function merely_viewing_a_hotel_does_not_overwrite_what_is_remembered(): void
    {
        $this->memory->apply($this->panelWithSearch(), self::TODAY);

        $browsing = ['search_data' => ['htl_dtl' => ['id' => 99, 'id_category' => 100]]];
        $this->memory->apply($browsing, self::TODAY);

        $this->assertSame(21, $this->repository->find()->hotelId);
    }

    #[Test]
    public function a_blank_panel_is_filled_from_what_was_remembered(): void
    {
        $this->memory->apply($this->panelWithSearch(), self::TODAY);

        $restored = $this->memory->apply(['hotels_info' => []], self::TODAY);

        $this->assertSame('2026-10-01', $restored['search_data']['date_from']);
        $this->assertSame('2026-10-05', $restored['search_data']['date_to']);
        $this->assertSame(21, $restored['search_data']['htl_dtl']['id']);
        $this->assertSame(48, $restored['search_data']['htl_dtl']['id_category']);
        $this->assertSame('Liberia', $restored['search_data']['location']);
        $this->assertSame(46, $restored['search_data']['location_category_id']);
        $this->assertSame(2, $restored['search_data']['occupancy_adults']);
        $this->assertSame(1, $restored['search_data']['occupancy_children']);
    }

    /**
     * The stock template reads these two unguarded, so anything that creates
     * search_data has to supply them or it takes the page down.
     */
    #[Test]
    public function a_restored_panel_carries_the_keys_the_stock_template_reads(): void
    {
        $this->memory->apply($this->panelWithSearch(), self::TODAY);

        $restored = $this->memory->apply([], self::TODAY);

        $this->assertArrayHasKey('num_days', $restored['search_data']);
        $this->assertArrayHasKey('order_date_restrict', $restored['search_data']);
        $this->assertSame(4, $restored['search_data']['num_days']);
        $this->assertArrayHasKey('hotel_name', $restored['search_data']['htl_dtl']);
        $this->assertArrayHasKey('city', $restored['search_data']['htl_dtl']);
    }

    /**
     * The page being looked at wins: restoring a remembered hotel over the one
     * whose page this is would show the customer the wrong thing.
     */
    #[Test]
    public function what_the_page_already_knows_is_never_overwritten(): void
    {
        $this->memory->apply($this->panelWithSearch(), self::TODAY);

        $onAnotherHotel = ['search_data' => ['htl_dtl' => ['id' => 99, 'id_category' => 100]]];
        $restored = $this->memory->apply($onAnotherHotel, self::TODAY);

        $this->assertSame(99, $restored['search_data']['htl_dtl']['id']);
        // The dates it does not know are still worth filling in.
        $this->assertSame('2026-10-01', $restored['search_data']['date_from']);
    }

    /**
     * A check-in that has been and gone would pre-fill a search the shop then
     * rejects — worse than an empty field. The rest is still worth keeping.
     */
    #[Test]
    public function dates_that_have_passed_are_not_restored(): void
    {
        $this->memory->apply($this->panelWithSearch(), self::TODAY);

        $restored = $this->memory->apply([], '2026-10-02');

        $this->assertArrayNotHasKey('date_from', $restored['search_data']);
        $this->assertArrayNotHasKey('date_to', $restored['search_data']);
        $this->assertSame(21, $restored['search_data']['htl_dtl']['id']);
        $this->assertSame(2, $restored['search_data']['occupancy_adults']);
    }

    #[Test]
    public function a_panel_is_left_alone_when_nothing_is_remembered(): void
    {
        $panel = ['hotels_info' => []];

        $this->assertSame($panel, $this->memory->apply($panel, self::TODAY));
    }

    /**
     * @return array
     */
    private function panelWithSearch()
    {
        return [
            'search_data' => [
                'htl_dtl' => ['id' => 21, 'id_category' => 48, 'hotel_name' => 'Hacienda', 'city' => 'Liberia'],
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-05',
                'location' => 'Liberia',
                'location_category_id' => 46,
                'occupancies' => [['adults' => 2, 'children' => 1, 'child_ages' => [7]]],
            ],
        ];
    }
}
