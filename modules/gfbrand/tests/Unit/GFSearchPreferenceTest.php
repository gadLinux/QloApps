<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the remembered search value object.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFSearchPreference::class)]
class GFSearchPreferenceTest extends TestCase
{
    #[Test]
    public function it_survives_a_round_trip_through_an_array(): void
    {
        $original = $this->preference();

        $restored = GFSearchPreference::fromArray($original->toArray());

        $this->assertEquals($original, $restored);
    }

    #[Test]
    public function unknown_keys_are_ignored_and_missing_ones_take_defaults(): void
    {
        $preference = GFSearchPreference::fromArray(['dateFrom' => '2026-10-01', 'junk' => 'x']);

        $this->assertSame('2026-10-01', $preference->dateFrom);
        $this->assertSame('', $preference->dateTo);
        $this->assertSame(0, $preference->hotelId);
        $this->assertSame([], $preference->occupancies);
    }

    #[Test]
    public function a_preference_with_nothing_worth_restoring_is_empty(): void
    {
        $this->assertTrue((new GFSearchPreference())->isEmpty());
        $this->assertFalse($this->preference()->isEmpty());
    }

    /**
     * A hotel on its own is worth remembering even with no dates — it saves
     * the customer picking it again.
     */
    #[Test]
    public function a_hotel_alone_is_not_empty(): void
    {
        $preference = new GFSearchPreference();
        $preference->hotelId = 21;

        $this->assertFalse($preference->isEmpty());
    }

    #[Test]
    public function dates_from_before_today_are_stale(): void
    {
        $preference = $this->preference();

        $this->assertTrue($preference->datesAreStale('2026-10-05'));
        $this->assertFalse($preference->datesAreStale('2026-10-01'));
        $this->assertFalse($preference->datesAreStale('2026-09-30'));
    }

    #[Test]
    public function a_preference_without_dates_is_never_stale(): void
    {
        $preference = new GFSearchPreference();
        $preference->hotelId = 21;

        $this->assertFalse($preference->hasDates());
        $this->assertFalse($preference->datesAreStale('2030-01-01'));
    }

    #[Test]
    public function dropping_stale_dates_keeps_everything_else(): void
    {
        $withoutDates = $this->preference()->withoutDates();

        $this->assertSame('', $withoutDates->dateFrom);
        $this->assertSame('', $withoutDates->dateTo);
        $this->assertSame(21, $withoutDates->hotelId);
        $this->assertCount(2, $withoutDates->occupancies);
    }

    #[Test]
    public function it_totals_the_guests_across_rooms(): void
    {
        $preference = $this->preference();

        $this->assertSame(3, $preference->countAdults());
        $this->assertSame(1, $preference->countChildren());
    }

    #[Test]
    public function guest_totals_of_an_empty_occupancy_are_zero(): void
    {
        $preference = new GFSearchPreference();

        $this->assertSame(0, $preference->countAdults());
        $this->assertSame(0, $preference->countChildren());
    }

    /**
     * @return GFSearchPreference
     */
    private function preference()
    {
        $preference = new GFSearchPreference();
        $preference->hotelId = 21;
        $preference->hotelCategoryId = 48;
        $preference->locationCategoryId = 46;
        $preference->locationName = 'Liberia';
        $preference->dateFrom = '2026-10-01';
        $preference->dateTo = '2026-10-05';
        $preference->occupancies = [
            ['adults' => 2, 'children' => 1, 'child_ages' => [7]],
            ['adults' => 1, 'children' => 0, 'child_ages' => []],
        ];

        return $preference;
    }
}
