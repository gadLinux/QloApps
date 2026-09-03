<?php
/**
 * 2026 GF Experiences
 *
 * One establishment, as the importer and the storefront see it.
 *
 * A plain value object rather than an ObjectModel: establishments are stored on
 * ps_product, and adding fields to Product's definition would mean overriding a
 * core class. This carries the GF-specific fields; Product carries the rest.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFEstablishment
{
    const TYPE_HOTEL = 'HOTEL';
    const TYPE_RESTAURANT = 'RESTAURANT';
    const TYPE_EXPERIENCE = 'EXPERIENCE';

    const CERTIFICATION_DEDICATED = 'dedicated';
    const CERTIFICATION_OPTIONS = 'options';

    const CHANNEL_MANAGER_NOT_CONNECTED = 'not_connected';
    const CHANNEL_MANAGER_ACTIVE = 'active';
    const CHANNEL_MANAGER_INACTIVE = 'inactive';

    /** @var int|null Product id once persisted. */
    public $idProduct;

    /** @var string Stable id from the import source. */
    public $sourceId;

    /** @var string */
    public $name;

    /** @var string */
    public $description;

    /** @var string One of the TYPE_* constants. */
    public $type = self::TYPE_EXPERIENCE;

    /** @var string|null One of the CERTIFICATION_* constants, or null when unknown. */
    public $certification;

    /** @var string */
    public $country = '';

    /** @var string */
    public $city = '';

    /** @var string */
    public $destinationUrl = '';

    /** @var bool */
    public $hasChannelManager = false;

    /** @var string One of the CHANNEL_MANAGER_* constants. */
    public $channelManagerStatus = self::CHANNEL_MANAGER_NOT_CONNECTED;

    /** @var bool */
    public $featuredHome = false;

    /**
     * @return string[]
     */
    public static function getValidTypes()
    {
        return [self::TYPE_HOTEL, self::TYPE_RESTAURANT, self::TYPE_EXPERIENCE];
    }

    /**
     * Bookable in QloApps, rather than linking out to the establishment's own
     * site. Drives the conditional CTA in story 1.10.
     */
    public function isDirectlyBookable()
    {
        return $this->type === self::TYPE_HOTEL
            && $this->hasChannelManager
            && $this->channelManagerStatus === self::CHANNEL_MANAGER_ACTIVE;
    }

    public function isHotel()
    {
        return $this->type === self::TYPE_HOTEL;
    }
}
