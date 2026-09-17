<?php
/**
 * 2026 GF Experiences
 *
 * Assembles what the advisor and partner sections render — story 1.11, AC-4.
 *
 * The drawer (homepage) and the standalone routes (/advisors/, /partners/)
 * both render the same component, so the data they need is built once here:
 * one pass over the active records, in display order, with every decision a
 * card needs already made — glyph or photo, tel: target, whether the site
 * link exists at all.
 *
 * APPLICATION LAYER — orchestrates the ObjectModels; holds no SQL and builds
 * no URLs (the front controller owns Link).
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFAdvisorPartnerListing
{
    /** @var GfAdvisor[] */
    private $advisors;

    /** @var GfPartner[] */
    private $partners;

    /**
     * @param  GfAdvisor[] $advisors
     * @param  GfPartner[] $partners
     */
    public function __construct(array $advisors, array $partners)
    {
        $this->advisors = $advisors;
        $this->partners = $partners;
    }

    /**
     * Load the active records for a language, in display order.
     *
     * @param  int $idLang
     * @return GFAdvisorPartnerListing
     */
    public static function forLanguage($idLang)
    {
        return new self(
            GfAdvisor::getActiveOrdered($idLang),
            GfPartner::getActiveOrdered($idLang)
        );
    }

    /**
     * @return array[] One entry per advisor card, ready to render.
     */
    public function advisorCards()
    {
        $cards = [];

        foreach ($this->advisors as $advisor) {
            $cards[] = [
                'id' => (int) $advisor->id_gf_advisor,
                'name' => (string) $advisor->name,
                'regions' => (string) $advisor->regions_served,
                'bio' => (string) $advisor->bio,
                'image' => (string) $advisor->image,
                // The glyph is a designed state, not an error: no photograph
                // means a generic person silhouette (AC-9), never a broken
                // image icon. A filename with no file on disk must fall back
                // to the glyph too, or AC-9 breaks the moment a file goes
                // missing (as the seeded partner logos currently do).
                'has_image' => self::fileExists('advisors', (string) $advisor->image),
                // The dial form, not the display number: tel: links must not
                // carry the spaces and dashes humans read.
                'tel' => (string) $advisor->phone_e164,
                // Suppressed entirely when empty (the live site shipped dead
                // href="#" links): a card without a site simply has no link.
                // Also suppressed for anything but http(s) — core's isUrl
                // validation accepts a javascript: URL, and this is the last
                // gate before it becomes a real href.
                'website' => (string) $advisor->website_url,
                'has_website' => self::isHttpUrl((string) $advisor->website_url),
            ];
        }

        return $cards;
    }

    /**
     * @return array[] One entry per partner logo tile, ready to render.
     */
    public function partnerCards()
    {
        $cards = [];

        foreach ($this->partners as $partner) {
            $cards[] = [
                'id' => (int) $partner->id_gf_partner,
                'name' => (string) $partner->name,
                'description' => (string) $partner->description,
                'logo' => (string) $partner->logo,
                'has_logo' => self::fileExists('partners', (string) $partner->logo),
                'website' => (string) $partner->website_url,
                'has_website' => self::isHttpUrl((string) $partner->website_url),
                'category' => (string) $partner->category,
            ];
        }

        return $cards;
    }

    /**
     * @return bool
     */
    public function hasAdvisors()
    {
        return count($this->advisors) > 0;
    }

    /**
     * @return bool
     */
    public function hasPartners()
    {
        return count($this->partners) > 0;
    }

    /**
     * Whether a stored file name actually exists on disk. A name is set the
     * moment an upload or CSV seed points at one — this is what keeps a
     * missing file (a broken upload, a seed referencing a logo that was
     * never delivered) from ever reaching the "never a broken image" cards.
     *
     * @param  string $directory 'advisors' or 'partners'.
     * @param  string $fileName
     * @return bool
     */
    private static function fileExists($directory, $fileName)
    {
        if ($fileName === '') {
            return false;
        }

        $path = rtrim(_PS_ROOT_DIR_, '/') . '/uploads/establishments/' . $directory . '/' . basename($fileName);

        return is_file($path);
    }

    /**
     * Core's isUrl validation accepts a javascript: URL; this is the gate
     * that keeps one from ever becoming a rendered href.
     *
     * @param  string $url
     * @return bool
     */
    private static function isHttpUrl($url)
    {
        return $url !== '' && (bool) preg_match('#^https?://#i', $url);
    }
}
