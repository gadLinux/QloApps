<?php
/**
 * 2026 GF Experiences
 *
 * Where "Inquire to Book" goes — story 1.10, consumed by 1.9's listing and by
 * the booking pages.
 *
 * One place, because two surfaces send guests here and they must not drift.
 *
 * The id pass-through is a deliberate fix, not a port of the WordPress form:
 * that one makes the guest re-pick, by hand, the very hotel they just clicked
 * "inquire" on. Story 1.12 pre-fills from ?hotel=.
 *
 * PRESENTATION LAYER — it builds URLs, which needs Link.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFInquiryLink
{
    /** The controller story 1.12 will add. */
    const CONTROLLER = 'inquiry';

    /** @var Link */
    private $link;

    /**
     * @param Link|null $link Untyped on purpose. `Link $link = null` is an
     *        implicitly nullable parameter, which PHP 8.4 deprecates, and the
     *        `?Link` form it wants is a parse error on the PHP 5.6 that
     *        PrestaShop 1.6 still supports. Four older classes in this module
     *        carry the deprecated form; this one does not add to them.
     */
    public function __construct($link = null)
    {
        $this->link = $link ?: Context::getContext()->link;
    }

    /**
     * @param  int $idEstablishment 0 when the hotel is not known.
     * @return string
     */
    public function forEstablishment($idEstablishment = 0)
    {
        if (!$this->isAvailable()) {
            return $this->link->getPageLink('contact', true);
        }

        $params = [];

        if ((int) $idEstablishment > 0) {
            $params['hotel'] = (int) $idEstablishment;
        }

        return $this->link->getModuleLink('gfbrand', self::CONTROLLER, $params);
    }

    /**
     * Whether the questionnaire exists yet.
     *
     * TEMPORARY. Story 1.12 owns controllers/front/inquiry.php; until it
     * lands, every "Inquire to Book" would 404, which is worse than sending
     * the guest to the contact page. When 1.12 ships, delete this method and
     * the branch above it — not the getModuleLink call.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return file_exists(
            _PS_MODULE_DIR_ . 'gfbrand/controllers/front/' . self::CONTROLLER . '.php'
        );
    }
}
