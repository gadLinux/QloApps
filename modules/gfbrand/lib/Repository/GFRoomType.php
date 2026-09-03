<?php
/**
 * 2026 GF Experiences
 *
 * One bookable room type belonging to an establishment.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFRoomType
{
    /** @var string Source id of the establishment this belongs to. */
    public $hotelSourceId;

    /** @var string Short code, unique within the hotel. */
    public $code;

    /** @var string */
    public $name;

    /** @var string */
    public $description = '';

    /** @var float Nightly rate, tax excluded. */
    public $price = 0.0;

    /** @var int */
    public $adults = 2;

    /** @var int */
    public $children = 0;

    /** @var int Physical rooms of this type to create. */
    public $roomCount = 1;

    /**
     * Stable id for this room type, derived from the hotel it belongs to.
     *
     * Room types are products, so they carry a gf_source_id like any other
     * imported row and are matched on it when the import runs again.
     *
     * @return string
     */
    public function getSourceId()
    {
        return $this->hotelSourceId . '-room-' . $this->code;
    }

    /**
     * Total guests the room sleeps.
     *
     * @return int
     */
    public function getMaxGuests()
    {
        return (int) $this->adults + (int) $this->children;
    }
}
