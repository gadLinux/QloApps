<?php
/**
 * 2026 GF Experiences
 *
 * Finds the seed photograph for an establishment.
 *
 * The source file names its images by a path relative to wherever it was
 * authored, so only the file name is dependable. This searches the places the
 * images can legitimately live, in order:
 *
 *   1. uploads/establishments/**   — the repository's own asset library,
 *      bind-mounted read-only in development. One source of truth, and where
 *      the client adds new photographs.
 *   2. the module's data/images/   — a self-contained copy, for a shop where
 *      that mount does not exist.
 *
 * Images are read only while an import runs; PrestaShop copies what it keeps
 * into img/p/, so nothing serves from these paths afterwards.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFImageLocator
{
    /** @var string[] Absolute directories to search, in priority order. */
    private $searchPaths;

    /**
     * @param string[] $searchPaths Overrides the defaults; used by tests.
     */
    public function __construct(array $searchPaths = null)
    {
        $this->searchPaths = $searchPaths ?: $this->getDefaultSearchPaths();
    }

    /**
     * Absolute path to the image, or null when it cannot be found.
     *
     * A missing image is not an error: the establishment still imports, it
     * just has no photograph.
     *
     * @param  string $fileName Bare file name, e.g. "hacienda-guachupelin.jpg".
     * @return string|null
     */
    public function locate($fileName)
    {
        $fileName = basename(trim((string) $fileName));

        if ($fileName === '') {
            return null;
        }

        foreach ($this->searchPaths as $directory) {
            $candidate = rtrim($directory, '/') . '/' . $fileName;

            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function getDefaultSearchPaths()
    {
        $uploads = _PS_ROOT_DIR_ . '/uploads/establishments';
        $moduleImages = _PS_MODULE_DIR_ . 'gfbrand/data/images';

        return [
            $uploads . '/hotels',
            $uploads . '/restaurants',
            $uploads,
            $moduleImages,
        ];
    }
}
