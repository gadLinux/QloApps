<?php
/**
 * 2026 GF Experiences
 *
 * Keeps a customer's last search in their cookie.
 *
 * The cookie is the right home for this: PrestaShop already sets one for every
 * visitor, it is readable in PHP before the page renders — so the form comes
 * back filled in with no flicker and no JavaScript — and it works for guests,
 * who are most of the people searching.
 *
 * DOMAIN LAYER — the only place that knows how a preference is stored.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFSearchPreferenceRepository
{
    /** Field within PrestaShop's own cookie. */
    const COOKIE_KEY = 'gfbrand_search';

    /**
     * Everything the shop remembers shares one HTTP cookie, and browsers cap
     * that at about 4KB. A search that will not fit in this much is not worth
     * evicting the cart for.
     */
    const MAX_PAYLOAD = 1024;

    /** @var Cookie */
    private $cookie;

    /**
     * @param Cookie $cookie PrestaShop's per-visitor cookie.
     */
    public function __construct($cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * @return GFSearchPreference|null Null when nothing usable is stored.
     */
    public function find()
    {
        $payload = $this->cookie->{self::COOKIE_KEY};

        if (!$payload) {
            return null;
        }

        $json = base64_decode($payload, true);

        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            return null;
        }

        $preference = GFSearchPreference::fromArray($data);

        return $preference->isEmpty() ? null : $preference;
    }

    /**
     * @return bool True when it was stored.
     */
    public function save(GFSearchPreference $preference)
    {
        if ($preference->isEmpty()) {
            return false;
        }

        // Base64 because Cookie::__set throws on "|" and "¤" — its own field
        // separators — and a hotel or location name is free text.
        $payload = base64_encode(json_encode($preference->toArray()));

        if (Tools::strlen($payload) > self::MAX_PAYLOAD) {
            return false;
        }

        $this->cookie->{self::COOKIE_KEY} = $payload;

        return true;
    }

    public function forget()
    {
        unset($this->cookie->{self::COOKIE_KEY});
    }
}
