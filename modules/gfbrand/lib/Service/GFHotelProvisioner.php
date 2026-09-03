<?php
/**
 * 2026 GF Experiences
 *
 * Gives a HOTEL establishment its bookable structure.
 *
 * Sits between the importer and the two factories so the importer keeps one
 * job — walking the source file — and this keeps the other: deciding what a
 * hotel needs and in what order.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFHotelProvisioner
{
    /** @var GFHotelFactory */
    private $hotelFactory;

    /** @var GFRoomTypeFactory */
    private $roomTypeFactory;

    /** @var GFHotelRepository */
    private $hotelRepository;

    /** @var array<string, GFRoomType[]> Room types by hotel source id. */
    private $roomTypesByHotel;

    /**
     * @param array<string, GFRoomType[]> $roomTypesByHotel
     */
    public function __construct(
        GFHotelFactory $hotelFactory,
        GFRoomTypeFactory $roomTypeFactory,
        GFHotelRepository $hotelRepository,
        array $roomTypesByHotel = []
    ) {
        $this->hotelFactory = $hotelFactory;
        $this->roomTypeFactory = $roomTypeFactory;
        $this->hotelRepository = $hotelRepository;
        $this->roomTypesByHotel = $roomTypesByHotel;
    }

    /**
     * True when this establishment should get a hotel structure and the
     * platform can give it one.
     */
    public function supports(GFEstablishment $establishment)
    {
        return $establishment->isHotel() && $this->hotelRepository->isAvailable();
    }

    /**
     * Create the hotel and its room types.
     *
     * @return int Number of room types provisioned.
     * @throws GFImportException
     */
    public function provision(GFEstablishment $establishment)
    {
        $idHotel = $this->hotelFactory->persist($establishment);
        $idCategory = $this->getHotelCategoryId($idHotel);

        $roomTypes = isset($this->roomTypesByHotel[$establishment->sourceId])
            ? $this->roomTypesByHotel[$establishment->sourceId]
            : [];

        foreach ($roomTypes as $roomType) {
            $this->roomTypeFactory->persist(
                $roomType,
                $idHotel,
                $idCategory,
                $establishment->imageFile
            );
        }

        return count($roomTypes);
    }

    /**
     * Remove the hotel rows the importer created. Room types are products and
     * are removed with the rest of the catalogue by the establishment importer.
     *
     * @return int Number of hotels deleted.
     */
    public function deleteImportedHotels()
    {
        if (!$this->hotelRepository->isAvailable()) {
            return 0;
        }

        $this->ensureControllerForDeletion();

        $deleted = 0;

        foreach ($this->hotelRepository->findImportedIds() as $idHotel) {
            $hotel = new HotelBranchInformation($idHotel);

            if (Validate::isLoadedObject($hotel) && $hotel->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * HotelBranchInformation::delete() collects its failures on
     * $context->controller->errors and calls count() on it unguarded, so it
     * fatals when there is no controller — which is the case on the CLI and
     * under PHPUnit. Give it something that satisfies that contract.
     */
    private function ensureControllerForDeletion()
    {
        $context = Context::getContext();

        if (isset($context->controller) && isset($context->controller->errors)) {
            return;
        }

        $context->controller = new GFErrorCollectingController();
    }

    private function getHotelCategoryId($idHotel)
    {
        $hotel = new HotelBranchInformation((int) $idHotel);

        return (int) $hotel->id_category;
    }
}
