<?php
/**
 * 2026 GF Experiences
 *
 * Integration tests for the hotel structure the importer provisions.
 *
 * The shape of the category tree is not cosmetic. WkRoomSearchHelper only
 * populates its template variables when the hotel's category sits under
 * PS_LOCATIONS_CATEGORY, and the stock template then reads them unguarded — so
 * a hotel rooted anywhere else takes every one of its room-type pages down
 * with a PHP 8 TypeError. That regression is what this file pins.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFCategoryTreeBuilder::class)]
#[CoversClass(GFHotelFactory::class)]
class GFHotelProvisioningTest extends TestCase
{
    /** Levels QloApps' own hotel form creates below Locations. */
    const LEVELS_BELOW_LOCATIONS = 4;

    /** @var GFHotelFactory */
    private $factory;

    /** @var GFHotelRepository */
    private $repository;

    /** @var int[] Hotels this test created, removed afterwards. */
    private $created = [];

    protected function setUp(): void
    {
        if (!(new GFHotelRepository())->isAvailable()) {
            $this->markTestSkipped('hotelreservationsystem is not installed.');
        }

        $this->repository = new GFHotelRepository();
        $this->factory = new GFHotelFactory(new GFCategoryTreeBuilder(), $this->repository);
    }

    protected function tearDown(): void
    {
        $this->ensureControllerForDeletion();

        foreach ($this->created as $idHotel) {
            $hotel = new HotelBranchInformation($idHotel);

            if (Validate::isLoadedObject($hotel)) {
                $hotel->delete();
            }
        }

        $this->created = [];
    }

    #[Test]
    public function a_provisioned_hotel_sits_under_the_locations_category(): void
    {
        $hotel = $this->provision();
        $category = new Category((int) $hotel->id_category);

        // hasParent() returns the ancestor's id, not a boolean; the caller in
        // WkRoomSearchHelper tests it for truthiness, so this does too.
        $this->assertNotEmpty(
            $category->hasParent((int) Configuration::get('PS_LOCATIONS_CATEGORY')),
            'The room search block skips a hotel that is not under Locations, '
            . 'and the stock template then fatals on the values it never set.'
        );
    }

    /**
     * Country, state, city, hotel — the same four the back office builds, so
     * the location autocomplete (which filters on depth) sees our hotels.
     */
    #[Test]
    public function the_tree_is_as_deep_as_a_hand_made_hotels(): void
    {
        $hotel = $this->provision();

        $locations = new Category((int) Configuration::get('PS_LOCATIONS_CATEGORY'));
        $category = new Category((int) $hotel->id_category);

        $this->assertSame(
            (int) $locations->level_depth + self::LEVELS_BELOW_LOCATIONS,
            (int) $category->level_depth
        );
    }

    #[Test]
    public function provisioning_twice_reuses_the_same_category(): void
    {
        $first = $this->provision();
        $second = $this->provision();

        $this->assertSame((int) $first->id_category, (int) $second->id_category);
    }

    /**
     * @return HotelBranchInformation
     */
    private function provision()
    {
        $establishment = new GFEstablishment();
        $establishment->sourceId = 'test-hotel-tree';
        $establishment->type = GFEstablishment::TYPE_HOTEL;
        $establishment->name = 'Test Tree Hotel';
        $establishment->country = 'Canada';
        $establishment->city = 'Edmonton AB';
        $establishment->description = 'Fixture.';

        $idHotel = $this->factory->persist($establishment);
        $this->created[] = $idHotel;

        return new HotelBranchInformation($idHotel);
    }

    /**
     * HotelBranchInformation::delete() counts errors on the controller without
     * checking there is one, and under PHPUnit there is not.
     */
    private function ensureControllerForDeletion()
    {
        $context = Context::getContext();

        if (isset($context->controller) && isset($context->controller->errors)) {
            return;
        }

        $context->controller = new GFErrorCollectingController();
    }
}
