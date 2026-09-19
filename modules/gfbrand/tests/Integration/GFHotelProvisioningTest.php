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
#[CoversClass(GFRoomTypeFactory::class)]
class GFHotelProvisioningTest extends TestCase
{
    /** Levels QloApps' own hotel form creates below Locations. */
    const LEVELS_BELOW_LOCATIONS = 4;

    /** A photograph that exists in the mounted asset library. */
    const HOTEL_PHOTOGRAPH = 'hacienda-guachupelin.jpg';

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

    /**
     * This has been asked for both ways, so it is pinned: a room type with no
     * photograph of its own gets a generated placeholder, NOT its hotel's
     * picture — which would put the same image on every room of the hotel.
     * The hotel's own photograph lives in htl_image, checked below.
     *
     * A placeholder is identified by its shape: the generator draws a square,
     * and the source photographs are landscape.
     */
    #[Test]
    public function a_room_with_no_photograph_gets_a_placeholder_not_the_hotels_picture(): void
    {
        $hotel = $this->provision(self::HOTEL_PHOTOGRAPH);

        $idProduct = $this->provisionRoomType($hotel, $this->establishment(self::HOTEL_PHOTOGRAPH));
        $size = $this->coverImageSize($idProduct);

        $this->assertNotNull($size, 'The room type was left with no image at all.');
        $this->assertSame(
            [GFPlaceholderImageGenerator::WIDTH, GFPlaceholderImageGenerator::HEIGHT],
            $size,
            'The room type is showing a photograph — it borrowed the hotel\'s.'
        );
    }

    #[Test]
    public function the_hotel_itself_keeps_its_photograph(): void
    {
        $hotel = $this->provision(self::HOTEL_PHOTOGRAPH);

        $images = (new HotelImage())->getImagesByHotelId((int) $hotel->id);

        $this->assertNotEmpty($images, 'The hotel has no htl_image row of its own.');
    }

    #[Test]
    public function provisioning_twice_reuses_the_same_category(): void
    {
        $first = $this->provision();
        $second = $this->provision();

        $this->assertSame((int) $first->id_category, (int) $second->id_category);
    }

    /**
     * @param  string $imageFile Bare file name, or '' for a hotel with none.
     * @return HotelBranchInformation
     */
    private function provision($imageFile = '')
    {
        $factory = $imageFile === ''
            ? $this->factory
            : new GFHotelFactory(
                new GFCategoryTreeBuilder(),
                $this->repository,
                new GFHotelImageFactory(new GFImageLocator())
            );

        $idHotel = $factory->persist($this->establishment($imageFile));
        $this->created[] = $idHotel;

        return new HotelBranchInformation($idHotel);
    }

    /**
     * @return GFEstablishment
     */
    private function establishment($imageFile = '')
    {
        $establishment = new GFEstablishment();
        $establishment->sourceId = 'test-hotel-tree';
        $establishment->type = GFEstablishment::TYPE_HOTEL;
        $establishment->name = 'Test Tree Hotel';
        $establishment->country = 'Canada';
        $establishment->city = 'Edmonton AB';
        $establishment->description = 'Fixture.';
        $establishment->imageFile = $imageFile;

        return $establishment;
    }

    /**
     * @return int id_product of the room type.
     */
    private function provisionRoomType(HotelBranchInformation $hotel, GFEstablishment $establishment)
    {
        $roomType = new GFRoomType();
        $roomType->hotelSourceId = $establishment->sourceId;
        $roomType->code = 'TST';
        $roomType->name = 'Test Room';
        $roomType->description = 'Fixture.';
        $roomType->price = 100.0;
        $roomType->roomCount = 1;
        // No imageFile: this is the case under test.

        $factory = new GFRoomTypeFactory(
            new GFEstablishmentRepository(),
            new GFProductImageFactory(new GFImageLocator(), new GFPlaceholderImageGenerator())
        );

        return $factory->persist($roomType, (int) $hotel->id, (int) $hotel->id_category, $establishment);
    }

    /**
     * @return int[]|null [width, height] of the product's cover image.
     */
    private function coverImageSize($idProduct)
    {
        $idImage = (int) Db::getInstance()->getValue(
            'SELECT `id_image` FROM `' . _DB_PREFIX_ . 'image` WHERE `id_product` = ' . (int) $idProduct
        );

        if (!$idImage) {
            return null;
        }

        $path = _PS_PROD_IMG_DIR_ . (new Image($idImage))->getExistingImgPath() . '.jpg';
        $size = @getimagesize($path);

        return $size === false ? null : [$size[0], $size[1]];
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
