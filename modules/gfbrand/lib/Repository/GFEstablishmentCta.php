<?php
/**
 * 2026 GF Experiences
 *
 * What a guest can actually DO at an establishment — story 1.10, FR-20.
 *
 * Three states, decided from data alone:
 *
 *   VISIT    somewhere you go: a restaurant or an experience with its own site.
 *   INQUIRE  a hotel we cannot yet take a booking for. Today that is all of
 *            them, so this is the common case.
 *   BOOK     a hotel with a live channel manager, bookable here.
 *
 * BOOK has no live consumer yet, and is built anyway: a hotel switching to
 * live booking must be a flag change in the back office, not a development
 * ticket. That is the entire reason the platform migration exists, and it is
 * AC-4.
 *
 * DOMAIN LAYER — it decides the branch and names it. It builds no URLs beyond
 * the external one it is handed: the other two are internal routes, which is
 * the front controller's business.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFEstablishmentCta
{
    /** Off-site, to the establishment's own website. */
    const VISIT = 'visit';

    /** To the booking questionnaire, carrying this establishment's id. */
    const INQUIRE = 'inquire';

    /** Into the native QloApps booking flow. */
    const BOOK = 'book';

    /** Nothing to offer — see the constructor. */
    const NONE = 'none';

    /** @var string */
    private $kind;

    /** @var string */
    private $externalUrl;

    /**
     * @param string $type                 One of GFEstablishment::TYPE_*.
     * @param string $destinationUrl       The establishment's own website.
     * @param mixed  $hasChannelManager    Flag as the database returns it.
     * @param string $channelManagerStatus One of GFEstablishment::CHANNEL_MANAGER_*.
     */
    public function __construct($type, $destinationUrl, $hasChannelManager, $channelManagerStatus)
    {
        $this->externalUrl = trim((string) $destinationUrl);
        $this->kind = $this->decide(
            (string) $type,
            $hasChannelManager,
            (string) $channelManagerStatus
        );
    }

    /**
     * @return string One of the class constants.
     */
    public function getKind()
    {
        return $this->kind;
    }

    /**
     * False when there is nothing to offer, so the card renders its footer
     * without a button rather than with a dead one.
     */
    public function isPresent()
    {
        return $this->kind !== self::NONE;
    }

    /**
     * @return string Empty unless the branch is VISIT.
     */
    public function getExternalUrl()
    {
        return $this->kind === self::VISIT ? $this->externalUrl : '';
    }

    /**
     * Only the off-site branch leaves the site, and only it therefore needs
     * target/rel and the screen-reader hint that goes with them.
     */
    public function opensInNewTab()
    {
        return $this->kind === self::VISIT;
    }

    /**
     * The button's words.
     *
     * Returned untranslated: this is the domain layer, and the template runs
     * the result through {l}. Keeping the English here means the branch and
     * its wording cannot drift apart.
     *
     * @return string
     */
    public function getLabel()
    {
        switch ($this->kind) {
            case self::VISIT:
                return 'Visit Website';
            case self::INQUIRE:
                return 'Inquire to Book';
            case self::BOOK:
                return 'Book Now';
            default:
                return '';
        }
    }

    /**
     * @return string
     */
    private function decide($type, $hasChannelManager, $channelManagerStatus)
    {
        if ($type !== GFEstablishment::TYPE_HOTEL) {
            // Restaurants, experiences and anything a later import invents.
            // Never bookable: offering a booking we cannot honour is a worse
            // failure than offering no button.
            return $this->externalUrl === '' ? self::NONE : self::VISIT;
        }

        // Both halves are required. A stale 'active' on a hotel whose flag was
        // turned off must not open the booking flow, and a flag set on a hotel
        // whose connection has lapsed must not either.
        $connected = (bool) $hasChannelManager
            && $channelManagerStatus === GFEstablishment::CHANNEL_MANAGER_ACTIVE;

        if ($connected) {
            return self::BOOK;
        }

        // Deliberately ignores the hotel's own website: sending the guest
        // off-site is precisely the booking we are trying to keep.
        return self::INQUIRE;
    }
}
