<?php
/**
 * 2026 GF Experiences
 *
 * What to draw when a product has no photograph.
 *
 * Carries the three things a placeholder needs — what it says, what it says
 * underneath, and a stable key that decides which brand ground it gets — so
 * the image factory takes one collaborator instead of three loose strings.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFImagePlaceholder
{
    /** @var string Headline, normally the product name. */
    public $label;

    /**
     * @var string Anything stable and unique per product — the source id.
     *      Two products must not share one, or their placeholders match.
     */
    public $variantKey;

    /** @var string Optional second line, normally the parent establishment. */
    public $sublabel;

    public function __construct($label, $variantKey, $sublabel = '')
    {
        $this->label = (string) $label;
        $this->variantKey = (string) $variantKey;
        $this->sublabel = (string) $sublabel;
    }

    /**
     * A placeholder with nothing to say is not worth drawing.
     *
     * @return bool
     */
    public function isDrawable()
    {
        return $this->label !== '' && $this->variantKey !== '';
    }
}
