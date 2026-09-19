<?php
/**
 * 2026 GF Experiences
 *
 * The country filter is derived from the data, never declared in code.
 *
 * The live site hardcodes SHOW ALL · USA · CANADA · SPAIN · COSTA RICA. That
 * list is what these tests exist to prevent: adding an establishment in
 * Portugal must add a Portugal pill with no code change (story 1.9 AC-2).
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFCountryFilter::class)]
class GFCountryFilterTest extends TestCase
{
    #[Test]
    public function with_nothing_requested_it_shows_everything(): void
    {
        $filter = new GFCountryFilter(['Canada', 'Spain'], null);

        $this->assertTrue($filter->isShowingAll());
        $this->assertSame('', $filter->getSelected());
    }

    #[Test]
    public function a_requested_country_is_selected(): void
    {
        $filter = new GFCountryFilter(['Canada', 'Spain'], 'Spain');

        $this->assertFalse($filter->isShowingAll());
        $this->assertSame('Spain', $filter->getSelected());
    }

    /**
     * Links get typed, shared and lower-cased by mail clients. Matching the
     * pill on case would show an empty grid for a country we do stock.
     */
    #[Test]
    public function matching_ignores_case_and_surrounding_space(): void
    {
        $filter = new GFCountryFilter(['Costa Rica'], '  costa rica ');

        $this->assertSame(
            'Costa Rica',
            $filter->getSelected(),
            'The canonical spelling from the data should win, not what was typed.'
        );
    }

    /**
     * AC-6. A hand-typed country we do not stock is not an error: the page
     * renders with the empty state, so the visitor can get back to Show All.
     */
    #[Test]
    public function an_unknown_country_stays_selected_so_the_empty_state_can_name_it(): void
    {
        $filter = new GFCountryFilter(['Canada'], 'Portugal');

        $this->assertFalse($filter->isShowingAll());
        $this->assertSame('Portugal', $filter->getSelected());
        $this->assertFalse($filter->isAvailable());
    }

    #[Test]
    public function an_empty_request_is_the_same_as_no_request(): void
    {
        $filter = new GFCountryFilter(['Canada'], '   ');

        $this->assertTrue($filter->isShowingAll());
    }

    /**
     * AC-2: one pill per country in the data, plus Show all. The order is
     * alphabetical rather than the live site's arbitrary sequence, because a
     * derived list needs a rule a reader can predict.
     */
    #[Test]
    public function the_pills_are_show_all_plus_one_per_country(): void
    {
        $filter = new GFCountryFilter(['USA', 'Canada', 'Costa Rica'], null);

        $labels = array_column($filter->getPills(), 'label');

        $this->assertSame(['Show All', 'Canada', 'Costa Rica', 'USA'], $labels);
    }

    #[Test]
    public function show_all_is_the_active_pill_when_nothing_is_selected(): void
    {
        $filter = new GFCountryFilter(['Canada', 'Spain'], null);

        $pills = $filter->getPills();

        $this->assertTrue($pills[0]['active'], 'Show All should be pressed.');
        $this->assertSame([false, false], [$pills[1]['active'], $pills[2]['active']]);
    }

    #[Test]
    public function the_selected_country_is_the_active_pill(): void
    {
        $filter = new GFCountryFilter(['Canada', 'Spain'], 'spain');

        $active = array_values(array_filter($filter->getPills(), function ($pill) {
            return $pill['active'];
        }));

        $this->assertCount(1, $active, 'Exactly one pill carries aria-pressed="true".');
        $this->assertSame('Spain', $active[0]['label']);
    }

    /**
     * The value goes into ?country=, so it must be the canonical spelling —
     * not the label, which is decorated for Show All.
     */
    #[Test]
    public function the_show_all_pill_carries_no_country_value(): void
    {
        $pills = (new GFCountryFilter(['Canada'], null))->getPills();

        $this->assertSame('', $pills[0]['value']);
        $this->assertSame('Canada', $pills[1]['value']);
    }

    /**
     * Establishments whose country was never filled in must not produce a
     * nameless pill that filters to nothing.
     */
    #[Test]
    public function blank_countries_in_the_data_produce_no_pill(): void
    {
        $filter = new GFCountryFilter(['Canada', '', '  ', 'Spain'], null);

        $this->assertSame(
            ['Show All', 'Canada', 'Spain'],
            array_column($filter->getPills(), 'label')
        );
    }

    #[Test]
    public function a_country_repeated_in_the_data_produces_one_pill(): void
    {
        $filter = new GFCountryFilter(['Canada', 'Canada', 'Spain'], null);

        $this->assertSame(
            ['Show All', 'Canada', 'Spain'],
            array_column($filter->getPills(), 'label')
        );
    }
}
