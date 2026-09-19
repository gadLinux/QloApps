<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the homepage assembly — story 1.13, AC-4.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFHomepage::class)]
class GFHomepageTest extends TestCase
{
    #[Test]
    public function the_featured_strip_returns_nothing_when_nothing_is_flagged(): void
    {
        $repo = $this->repositoryWithFeatured([]);
        $homepage = new GFHomepage($repo);

        $this->assertSame([], $homepage->featuredCards(1));
    }

    #[Test]
    public function the_featured_strip_builds_cards_from_the_flagged_rows(): void
    {
        $rows = [
            [
                'id_product' => 10,
                'gf_source_id' => '10',
                'gf_type' => 'HOTEL',
                'gf_country' => 'Spain',
                'gf_city' => 'Madrid',
                'gf_destination_url' => 'https://example.com',
                'gf_certification' => 'dedicated',
                'gf_has_channel_manager' => 1,
                'gf_channel_manager_status' => 'active',
                'name' => 'Hotel Madrid GF',
                'description_short' => 'A gluten-free hotel.',
                'link_rewrite' => 'hotel-madrid-gf',
                'id_image' => 3,
            ],
        ];

        $repo = $this->repositoryWithFeatured($rows);
        $homepage = new GFHomepage($repo);

        $cards = $homepage->featuredCards(1);

        $this->assertCount(1, $cards);
        $this->assertSame('Hotel Madrid GF', $cards[0]['name']);
        $this->assertSame('Hotel', $cards[0]['type_label']);
        $this->assertSame('GF Dedicated', $cards[0]['certification_label']);
        $this->assertSame('Madrid', $cards[0]['city']);
    }

    /**
     * The homepage card is the same component as the listing, in its compact
     * variant — so the card shape must agree with what the listing produces.
     */
    #[Test]
    public function the_featured_card_agrees_with_the_listing_card_shape(): void
    {
        $row = [
            'id_product' => 5,
            'gf_source_id' => '5',
            'gf_type' => 'RESTAURANT',
            'gf_country' => '',
            'gf_city' => '',
            'gf_destination_url' => '',
            'gf_certification' => null,
            'gf_has_channel_manager' => 0,
            'gf_channel_manager_status' => '',
            'name' => 'Bakery',
            'description_short' => '',
            'link_rewrite' => 'bakery',
            'id_image' => 0,
        ];

        $homepage = new GFHomepage($this->repositoryWithFeatured([$row]));
        $listing = new GFEstablishmentListing($this->repositoryWithFeatured([]));

        $homeCard = $homepage->featuredCards(1)[0];
        $listCard = $listing->toCardForTest($row);

        $this->assertSame(array_keys($listCard), array_keys($homeCard));
    }

    private function repositoryWithFeatured(array $rows)
    {
        $repo = $this->createMock(GFEstablishmentRepository::class);
        $repo->method('findFeaturedForHome')->willReturn($rows);

        return $repo;
    }
}
