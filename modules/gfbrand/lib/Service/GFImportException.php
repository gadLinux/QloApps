<?php
/**
 * 2026 GF Experiences
 *
 * Raised when an import cannot proceed at all — an unreadable source file,
 * a product that will not save. A single bad row does not raise this; it is
 * collected in the result instead.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFImportException extends Exception
{
}
