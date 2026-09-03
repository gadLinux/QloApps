<?php
/**
 * 2026 GF Experiences
 *
 * Creates the bookable room types belonging to a hotel.
 *
 * A room type is a product with booking_product set, joined to its hotel by
 * htl_room_type, plus one htl_room_information row per physical room. Without
 * the room rows the type exists but nothing can be booked into it.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFRoomTypeFactory
{
    /** Booking products are not stock-limited in QloApps. */
    const BOOKING_QUANTITY = 999999999;

    /** @var GFEstablishmentRepository Reused to tag room types with a source id. */
    private $productRepository;

    /** @var GFProductImageFactory|null */
    private $imageFactory;

    public function __construct(
        GFEstablishmentRepository $productRepository,
        GFProductImageFactory $imageFactory = null
    ) {
        $this->productRepository = $productRepository;
        $this->imageFactory = $imageFactory;
    }

    /**
     * Create or update one room type under a hotel.
     *
     * @param  int $idHotel
     * @param  int $idHotelCategory
     * @param  GFEstablishment|null $hotel The parent, whose photograph and
     *                name the room type borrows when it has none of its own.
     * @return int id_product of the room type
     * @throws GFImportException
     */
    public function persist(GFRoomType $roomType, $idHotel, $idHotelCategory, GFEstablishment $hotel = null)
    {
        $sourceId = $roomType->getSourceId();
        $existingId = $this->productRepository->findIdBySourceId($sourceId);

        $product = $existingId ? new Product($existingId) : new Product();
        $isNew = !$existingId;

        $this->applyProductFields($product, $roomType, $idHotelCategory, $isNew);

        if (!$product->save()) {
            throw new GFImportException('Could not save room type "' . $roomType->name . '"');
        }

        $idProduct = (int) $product->id;

        if ($isNew) {
            $product->addToCategories([$idHotelCategory]);
        }

        $this->persistRoomTypeLink($idProduct, $idHotel, $roomType);
        $this->persistRooms($idProduct, $idHotel, $roomType);
        $this->tagWithSourceId($idProduct, $sourceId, $roomType);
        $this->attachImage($idProduct, $roomType, $hotel);

        return $idProduct;
    }

    /**
     * A room type shows its own photograph when it has one.
     *
     * It deliberately does not borrow the hotel's: every room of a hotel would
     * then carry the same picture, which reads as a fault rather than as a
     * room list. A branded placeholder, distinct per room and plainly not a
     * photograph, is the honest stand-in until real room photography arrives.
     */
    private function attachImage($idProduct, GFRoomType $roomType, GFEstablishment $hotel = null)
    {
        if ($this->imageFactory === null) {
            return;
        }

        $placeholder = new GFImagePlaceholder(
            $roomType->name,
            $roomType->getSourceId(),
            $hotel !== null ? $hotel->name : ''
        );

        $this->imageFactory->attach($idProduct, $roomType->imageFile, $placeholder);
    }

    private function applyProductFields(Product $product, GFRoomType $roomType, $idHotelCategory, $isNew)
    {
        $shortDescription = Tools::substr(strip_tags($roomType->description), 0, 400);
        $linkRewrite = Tools::link_rewrite($roomType->name . '-' . $roomType->code);

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];

            $product->name[$idLang] = $roomType->name;
            $product->description[$idLang] = $roomType->description;
            $product->description_short[$idLang] = $shortDescription;
            $product->link_rewrite[$idLang] = $linkRewrite;
        }

        $product->price = (float) $roomType->price;

        if (!$isNew) {
            return;
        }

        $product->id_category_default = (int) $idHotelCategory;
        $product->active = 1;
        $product->booking_product = 1;
        $product->show_at_front = 1;
        $product->is_virtual = 1;
        $product->indexed = 1;
        $product->quantity = self::BOOKING_QUANTITY;
        $product->minimal_quantity = 1;
        $product->id_tax_rules_group = 0;
    }

    /**
     * The htl_room_type row is what makes the product a room type of this
     * hotel; without it the booking flow does not see it.
     */
    private function persistRoomTypeLink($idProduct, $idHotel, GFRoomType $roomType)
    {
        $existingId = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT `id` FROM `' . _DB_PREFIX_ . 'htl_room_type`
             WHERE `id_product` = ' . (int) $idProduct
        );

        $link = $existingId ? new HotelRoomType($existingId) : new HotelRoomType();

        $link->id_product = (int) $idProduct;
        $link->id_hotel = (int) $idHotel;
        $link->adults = (int) $roomType->adults;
        $link->children = (int) $roomType->children;
        $link->max_adults = (int) $roomType->adults;
        $link->max_children = (int) $roomType->children;
        $link->max_guests = $roomType->getMaxGuests();

        $link->save();
    }

    /**
     * One row per physical room. Existing rooms are counted rather than
     * recreated, so a re-import does not multiply a hotel's inventory.
     */
    private function persistRooms($idProduct, $idHotel, GFRoomType $roomType)
    {
        $existing = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_room_information`
             WHERE `id_product` = ' . (int) $idProduct
        );

        for ($number = $existing + 1; $number <= $roomType->roomCount; $number++) {
            $room = new HotelRoomInformation();
            $room->id_product = (int) $idProduct;
            $room->id_hotel = (int) $idHotel;
            $room->room_num = $roomType->code . '-' . str_pad($number, 2, '0', STR_PAD_LEFT);
            $room->id_status = 1;
            $room->floor = 'Ground';
            $room->save();
        }
    }

    /**
     * Room types are products, so they carry the same source id the importer
     * uses everywhere else — which is what a reload deletes them by.
     */
    private function tagWithSourceId($idProduct, $sourceId, GFRoomType $roomType)
    {
        $establishment = new GFEstablishment();
        $establishment->sourceId = $sourceId;
        $establishment->type = GFEstablishment::TYPE_HOTEL;
        $establishment->name = $roomType->name;

        $this->productRepository->saveFields($idProduct, $establishment);
    }
}
