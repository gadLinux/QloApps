<?php
/**
 * 2026 GF Experiences
 *
 * The establishments listing — story 1.9.
 *
 * These tests hold the page's policy: what the filter row contains, which
 * establishments a country selects, how the set pages, and what the empty
 * state is for. Everything here is server-side by construction, because D6
 * requires the filter to work with JavaScript disabled.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFEstablishmentListing::class)]
#[CoversClass(GFEstablishmentListingResult::class)]
class GFEstablishmentListingTest extends TestCase
{
    const LANG = 1;

    #[Test]
    public function it_lists_every_establishment_when_nothing_is_filtered(): void
    {
        $result = $this->listing($this->catalogue())->forRequest('', 1, self::LANG);

        $this->assertCount(5, $result->getEstablishments());
        $this->assertSame(5, $result->getTotal());
        $this->assertTrue($result->getFilter()->isShowingAll());
    }

    #[Test]
    public function the_pills_come_from_the_data(): void
    {
        $result = $this->listing($this->catalogue())->forRequest('', 1, self::LANG);

        $this->assertSame(
            ['Show All', 'Canada', 'Costa Rica', 'Spain'],
            array_column($result->getFilter()->getPills(), 'label')
        );
    }

    /**
     * AC-2, stated as the acceptance criterion states it: new data, no code.
     */
    #[Test]
    public function an_establishment_in_a_new_country_adds_its_pill(): void
    {
        $catalogue = $this->catalogue();
        $catalogue[] = $this->row('Pasteis Sem Gluten', 'Portugal', 'Lisbon');

        $labels = array_column(
            $this->listing($catalogue)->forRequest('', 1, self::LANG)->getFilter()->getPills(),
            'label'
        );

        $this->assertContains('Portugal', $labels);
    }

    #[Test]
    public function selecting_a_country_narrows_the_listing(): void
    {
        $result = $this->listing($this->catalogue())->forRequest('Canada', 1, self::LANG);

        $this->assertSame(3, $result->getTotal(), 'Two of the five are elsewhere.');
        $this->assertSame(
            ['Cantine Panella', 'Hotel Chateau Louis', 'Mako Foods'],
            array_column($result->getEstablishments(), 'name'),
            'Ordered by name, as the query orders them.'
        );
    }

    #[Test]
    public function the_filter_reaches_the_query_rather_than_being_applied_afterwards(): void
    {
        $repository = new GFFakeEstablishmentRepository($this->catalogue());

        (new GFEstablishmentListing($repository))->forRequest('canada', 1, self::LANG);

        $this->assertSame(
            'Canada',
            $repository->lastListingCall['country'],
            'The canonical country must be pushed into the query, not filtered in PHP: '
            . 'paging over a filtered set is wrong if the filter runs after the LIMIT.'
        );
    }

    #[Test]
    public function a_shared_lowercase_link_still_filters(): void
    {
        $result = $this->listing($this->catalogue())->forRequest('costa rica', 1, self::LANG);

        $this->assertSame(1, $result->getTotal());
        $this->assertSame('Costa Rica', $result->getFilter()->getSelected());
    }

    /* ---- Empty state (AC-6) ------------------------------------------- */

    #[Test]
    public function a_country_we_do_not_stock_is_empty_rather_than_unfiltered(): void
    {
        $result = $this->listing($this->catalogue())->forRequest('Portugal', 1, self::LANG);

        $this->assertTrue($result->isEmpty(), 'Showing everything would fake a working filter.');
        $this->assertSame(0, $result->getTotal());
        $this->assertSame('Portugal', $result->getFilter()->getSelected());
    }

    #[Test]
    public function an_empty_catalogue_is_empty_without_erroring(): void
    {
        $result = $this->listing([])->forRequest('', 1, self::LANG);

        $this->assertTrue($result->isEmpty());
        $this->assertSame(['Show All'], array_column($result->getFilter()->getPills(), 'label'));
    }

    /* ---- Paging (AC-7) ------------------------------------------------ */

    #[Test]
    public function it_pages_at_twelve(): void
    {
        $result = $this->listing($this->manyEstablishments(15))->forRequest('', 1, self::LANG);

        $this->assertCount(12, $result->getEstablishments());
        $this->assertSame(2, $result->getPagination()->getPageCount());
        $this->assertSame(15, $result->getTotal());
    }

    #[Test]
    public function the_last_page_carries_the_remainder(): void
    {
        $result = $this->listing($this->manyEstablishments(15))->forRequest('', 2, self::LANG);

        $this->assertCount(3, $result->getEstablishments());
    }

    /**
     * AC-7's second half: the filter state survives a page change. The count
     * and the page must both be of the filtered set, never of the catalogue.
     */
    #[Test]
    public function paging_happens_within_the_filtered_set(): void
    {
        $catalogue = array_merge(
            $this->manyEstablishments(15, 'Spain'),
            $this->manyEstablishments(4, 'Canada')
        );

        $result = $this->listing($catalogue)->forRequest('Canada', 1, self::LANG);

        $this->assertSame(4, $result->getTotal());
        $this->assertFalse($result->getPagination()->isNeeded());
    }

    /* ---- What a card is handed ---------------------------------------- */

    #[Test]
    public function each_card_carries_what_the_grid_renders(): void
    {
        $card = $this->listing($this->catalogue())->forRequest('Spain', 1, self::LANG)
            ->getEstablishments()[0];

        $this->assertSame('Hotel UMusic Madrid', $card['name']);
        $this->assertSame('Madrid', $card['city']);
        $this->assertSame('Spain', $card['country']);
        $this->assertSame(GFEstablishment::TYPE_HOTEL, $card['type']);
        $this->assertSame('Hotel', $card['type_label']);
    }

    /**
     * The pill above the name is read aloud and printed in caps by CSS, so it
     * is stored in sentence case rather than shouting from the data.
     */
    #[Test]
    public function the_type_label_is_readable_rather_than_an_enum(): void
    {
        $cards = $this->listing($this->catalogue())->forRequest('Canada', 1, self::LANG)
            ->getEstablishments();

        $this->assertSame(
            ['Restaurant', 'Hotel', 'Restaurant'],
            array_column($cards, 'type_label'),
            'Both stored enums become sentence case; CSS does the shouting.'
        );
    }

    #[Test]
    public function a_restaurant_with_its_own_site_links_out_to_it(): void
    {
        $card = $this->listing($this->catalogue())->forRequest('Costa Rica', 1, self::LANG)
            ->getEstablishments()[0];

        $this->assertSame(GFEstablishmentCta::VISIT, $card['cta']);
        $this->assertSame('https://example.test/gallos', $card['cta_url']);
        $this->assertTrue($card['cta_new_tab']);
    }

    /**
     * The decision itself is GFEstablishmentCta's, and tested there. This
     * pins that the card actually carries the answer.
     */
    #[Test]
    public function a_hotel_we_cannot_book_yet_invites_an_enquiry(): void
    {
        $card = $this->listing($this->catalogue())->forRequest('Spain', 1, self::LANG)
            ->getEstablishments()[0];

        $this->assertSame(GFEstablishmentCta::INQUIRE, $card['cta']);
        $this->assertSame('Inquire to Book', $card['cta_label']);
        $this->assertSame('', $card['cta_url'], 'The controller supplies the internal URL.');
    }

    #[Test]
    public function a_hotel_with_a_live_channel_manager_can_be_booked(): void
    {
        $bookable = $this->row('Bookable Hotel', 'Norway', 'Oslo', GFEstablishment::TYPE_HOTEL);
        $bookable['gf_has_channel_manager'] = 1;
        $bookable['gf_channel_manager_status'] = GFEstablishment::CHANNEL_MANAGER_ACTIVE;

        $card = $this->listing([$bookable])->forRequest('Norway', 1, self::LANG)
            ->getEstablishments()[0];

        $this->assertSame(GFEstablishmentCta::BOOK, $card['cta']);
        $this->assertSame('Book Now', $card['cta_label']);
    }

    /* ---- Certification pill (AC-7) ------------------------------------- */

    #[Test]
    public function the_two_certification_levels_are_named_differently(): void
    {
        $this->assertSame(
            'GF Dedicated',
            GFEstablishmentListing::labelForCertification(GFEstablishment::CERTIFICATION_DEDICATED)
        );
        $this->assertSame(
            'GF Options',
            GFEstablishmentListing::labelForCertification(GFEstablishment::CERTIFICATION_OPTIONS)
        );
    }

    /**
     * An establishment we have no certification answer for shows no pill.
     * Defaulting to either one would be a reassurance we have not earned.
     */
    #[Test]
    public function an_uncertified_establishment_gets_no_pill(): void
    {
        $row = $this->row('Unknown Place', 'Norway', 'Oslo');
        $row['gf_certification'] = null;

        $card = $this->listing([$row])->forRequest('Norway', 1, self::LANG)
            ->getEstablishments()[0];

        $this->assertSame('', $card['certification_label']);
    }

    /* ---- Fixtures ------------------------------------------------------ */

    private function listing(array $rows)
    {
        return new GFEstablishmentListing(new GFFakeEstablishmentRepository($rows));
    }

    private function catalogue()
    {
        return [
            $this->row('Cantine Panella', 'Canada', 'Montreal', GFEstablishment::TYPE_RESTAURANT, 'https://example.test/panella'),
            $this->row('Mako Foods', 'Canada', 'Longueuil', GFEstablishment::TYPE_RESTAURANT, 'https://example.test/mako'),
            $this->row('Gallos Restaurante', 'Costa Rica', 'San Jose', GFEstablishment::TYPE_RESTAURANT, 'https://example.test/gallos'),
            $this->row('Hotel UMusic Madrid', 'Spain', 'Madrid', GFEstablishment::TYPE_HOTEL, ''),
            $this->row('Hotel Chateau Louis', 'Canada', 'Edmonton', GFEstablishment::TYPE_HOTEL, ''),
        ];
    }

    /**
     * @return array[] $count establishments, all in one country.
     */
    private function manyEstablishments($count, $country = 'Spain')
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = $this->row(sprintf('Establishment %02d', $i), $country, 'Somewhere');
        }

        return $rows;
    }

    private function row($name, $country, $city, $type = GFEstablishment::TYPE_RESTAURANT, $url = '')
    {
        return [
            'id_product' => crc32($name),
            'name' => $name,
            'description_short' => $name . ' description.',
            'link_rewrite' => Tools::link_rewrite($name),
            'gf_type' => $type,
            'gf_country' => $country,
            'gf_city' => $city,
            'gf_destination_url' => $url,
            'gf_certification' => 'dedicated',
            'gf_has_channel_manager' => 0,
            'gf_channel_manager_status' => 'not_connected',
            'id_image' => 0,
        ];
    }
}
