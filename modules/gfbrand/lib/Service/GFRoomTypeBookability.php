<?php
/**
 * 2026 GF Experiences
 *
 * Which room types QloApps may take a booking for.
 *
 * QloApps assumes anything with room types is bookable, so the search results
 * and the room-detail page offer "Book Now" for every one of them. For a hotel
 * whose channel manager is not connected that is a booking we cannot honour —
 * and the same hotel says "Inquire to Book" on its own card, which is a
 * contradiction the guest can see.
 *
 * The decision is NOT re-implemented here. Each room type inherits the answer
 * GFEstablishmentCta gives for its hotel's establishment, so the card and the
 * booking flow cannot drift apart: there is one rule, in one class.
 *
 * "Restricted" means only "not bookable here". The room type keeps its page,
 * its photographs, its description and its prices — a guest can still see the
 * room and ask about it. What they cannot do is put it in a cart.
 *
 * APPLICATION LAYER — no SQL, no URLs, no output.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFRoomTypeBookability
{
    /** @var array<int, GFEstablishmentCta> Keyed by room-type id_product. */
    private $restricted = [];

    /** @var array<int, int> Room-type id_product => establishment id_product. */
    private $establishments = [];

    /**
     * @param array[] $rows From GFHotelRepository::findRoomTypeBookability().
     *                Room types of hotels we never imported are simply absent,
     *                and therefore untouched.
     */
    public function __construct(array $rows)
    {
        foreach ($rows as $row) {
            $this->consider($row);
        }
    }

    /**
     * True when this room type must not offer a booking.
     *
     * @param  int $idProduct
     * @return bool
     */
    public function isRestricted($idProduct)
    {
        return isset($this->restricted[(int) $idProduct]);
    }

    /**
     * @param  int $idProduct
     * @return string A GFEstablishmentCta kind, or '' when unrestricted.
     */
    public function kindFor($idProduct)
    {
        return $this->isRestricted($idProduct)
            ? $this->restricted[(int) $idProduct]->getKind()
            : '';
    }

    /**
     * @param  int $idProduct
     * @return string The button's words, or '' when unrestricted.
     */
    public function labelFor($idProduct)
    {
        return $this->isRestricted($idProduct)
            ? $this->restricted[(int) $idProduct]->getLabel()
            : '';
    }

    /**
     * The establishment behind this room type, so an enquiry can name the
     * hotel the guest was actually looking at.
     *
     * @param  int $idProduct
     * @return int 0 when unknown.
     */
    public function establishmentFor($idProduct)
    {
        $idProduct = (int) $idProduct;

        return isset($this->establishments[$idProduct]) ? $this->establishments[$idProduct] : 0;
    }

    /**
     * @return int[] Room-type product ids that may not be booked.
     */
    public function restrictedIds()
    {
        return array_keys($this->restricted);
    }

    private function consider(array $row)
    {
        $idProduct = (int) $row['id_product'];

        // No establishment row behind the hotel means no evidence that it is
        // connected. Absence of evidence is not permission to take money.
        $type = isset($row['gf_type']) && $row['gf_type'] !== null
            ? (string) $row['gf_type']
            : GFEstablishment::TYPE_HOTEL;

        $cta = new GFEstablishmentCta(
            $type,
            isset($row['gf_destination_url']) ? $row['gf_destination_url'] : '',
            isset($row['gf_has_channel_manager']) ? $row['gf_has_channel_manager'] : false,
            isset($row['gf_channel_manager_status']) ? $row['gf_channel_manager_status'] : ''
        );

        if ($cta->getKind() === GFEstablishmentCta::BOOK) {
            // Bookable: leave the stock flow entirely alone.
            return;
        }

        $this->restricted[$idProduct] = $cta;
        $this->establishments[$idProduct] = isset($row['id_establishment'])
            ? (int) $row['id_establishment']
            : 0;
    }
}
