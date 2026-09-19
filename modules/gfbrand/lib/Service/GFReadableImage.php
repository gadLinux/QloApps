<?php
/**
 * 2026 GF Experiences
 *
 * Hands back a path ImageManager can actually read.
 *
 * The supplied photographs are not always the format their extension claims —
 * the library currently holds a WebP and two PNGs all named .jpg — and
 * PrestaShop 1.6's ImageManager fatals on anything but JPEG, PNG or GIF.
 * Converting here keeps the client's library untouched and copes with whatever
 * they add next.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFReadableImage
{
    /** Formats PrestaShop 1.6's ImageManager opens without complaint. */
    private static $supported = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];

    /**
     * @param  string $sourcePath
     * @return string|null The original path when it is already readable, a
     *                temporary converted copy when it is not, null when the
     *                file is not an image at all.
     */
    public function pathFor($sourcePath)
    {
        $info = @getimagesize($sourcePath);

        if ($info === false) {
            return null;
        }

        if (in_array($info[2], self::$supported, true)) {
            return $sourcePath;
        }

        return $this->convert($sourcePath);
    }

    /**
     * Remove the converted copy, never the original.
     *
     * @param string $usedPath   What pathFor() returned.
     * @param string $sourcePath What was passed to it.
     */
    public function discard($usedPath, $sourcePath)
    {
        if ($usedPath !== $sourcePath && is_file($usedPath)) {
            unlink($usedPath);
        }
    }

    /**
     * @return string|null
     */
    private function convert($sourcePath)
    {
        $resource = @imagecreatefromstring(file_get_contents($sourcePath));

        if ($resource === false) {
            return null;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'gf-img-') . '.jpg';
        $converted = imagejpeg($resource, $temporaryPath, 90);
        imagedestroy($resource);

        return $converted ? $temporaryPath : null;
    }
}
