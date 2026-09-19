<?php
/**
 * 2026 GF Experiences
 *
 * One row of the partners seed file — story 1.11.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFPartnerSeed
{
    /** @var string Stable id from the source file; what an upsert matches on. */
    public $sourceId = '';

    /** @var string Name in the source file's language. */
    public $name = '';

    /** @var string Logo file name; '' renders a name tile, not a broken image. */
    public $logoFile = '';

    /** @var string */
    public $websiteUrl = '';

    /** @var string */
    public $description = '';

    /** @var string */
    public $category = '';

    /** @var int */
    public $position = 0;

    /** @var bool */
    public $active = true;
}
