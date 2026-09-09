<?php
/**
 * 2026 GF Experiences
 *
 * One row of the advisors seed file — story 1.11.
 *
 * A plain value object the importer hands to the model: it carries the
 * translator-agnostic source values (the name, in the file's language) and
 * the identity used to make the import re-runnable.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFAdvisorSeed
{
    /** @var string Stable id from the source file; what an upsert matches on. */
    public $sourceId = '';

    /** @var string Name in the source file's language. */
    public $name = '';

    /** @var string */
    public $regionsServed = '';

    /** @var string Human display form, e.g. "+34 669 77 14 72". */
    public $phone = '';

    /** @var string Dial form for tel: links, e.g. "+34669771472". */
    public $phoneE164 = '';

    /** @var string Empty until the client supplies the site (OQ-d). */
    public $websiteUrl = '';

    /** @var string */
    public $email = '';

    /** @var string File name only; '' means the card shows the glyph. */
    public $imageUrl = '';

    /** @var string */
    public $bio = '';

    /** @var int */
    public $position = 0;

    /** @var bool */
    public $active = true;
}
