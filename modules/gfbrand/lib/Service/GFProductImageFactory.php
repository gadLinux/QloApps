<?php
/**
 * 2026 GF Experiences
 *
 * Attaches a seed photograph to a product.
 *
 * PrestaShop stores product images as an ps_image row plus generated files
 * under img/p/. This copies the source file into that structure and builds the
 * theme's thumbnail sizes, so listings and the product page both have
 * something to show.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFProductImageFactory
{
    /** @var GFImageLocator */
    private $locator;

    public function __construct(GFImageLocator $locator)
    {
        $this->locator = $locator;
    }

    /**
     * Give a product its cover image, unless it already has one.
     *
     * Idempotent by design: a product that already carries an image is left
     * alone, so re-importing does not pile up duplicates or discard a
     * photograph uploaded through the back office.
     *
     * @param  int    $idProduct
     * @param  string $fileName Bare file name from the source data.
     * @return bool   True when an image was added.
     */
    public function attach($idProduct, $fileName)
    {
        if ($this->hasImage($idProduct)) {
            return false;
        }

        $sourcePath = $this->locator->locate($fileName);

        if ($sourcePath === null) {
            return false;
        }

        $readablePath = $this->toReadableJpeg($sourcePath);

        if ($readablePath === null) {
            return false;
        }

        $image = new Image();
        $image->id_product = (int) $idProduct;
        $image->position = 1;
        $image->cover = true;

        if (!$image->add()) {
            $this->discardTemporary($readablePath, $sourcePath);

            return false;
        }

        $written = $this->writeImageFiles($image, $readablePath);
        $this->discardTemporary($readablePath, $sourcePath);

        if (!$written) {
            $image->delete();

            return false;
        }

        $this->setLegend($image, $idProduct);

        return true;
    }

    /**
     * A path ImageManager can actually read, converting when it cannot.
     *
     * The supplied photographs are not always the format their extension
     * claims — the library currently holds a WebP and two PNGs all named
     * .jpg — and PrestaShop 1.6's ImageManager fatals on anything but JPEG,
     * PNG or GIF. Converting here keeps the client's library untouched and
     * copes with whatever they add next.
     *
     * @return string|null Path to read, or null when the file is not an image.
     */
    private function toReadableJpeg($sourcePath)
    {
        $info = @getimagesize($sourcePath);

        if ($info === false) {
            return null;
        }

        $supported = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];

        if (in_array($info[2], $supported, true)) {
            return $sourcePath;
        }

        $resource = @imagecreatefromstring(file_get_contents($sourcePath));

        if ($resource === false) {
            return null;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'gf-img-') . '.jpg';
        $converted = imagejpeg($resource, $temporaryPath, 90);
        imagedestroy($resource);

        return $converted ? $temporaryPath : null;
    }

    /**
     * Remove the converted copy, never the original.
     */
    private function discardTemporary($usedPath, $sourcePath)
    {
        if ($usedPath !== $sourcePath && is_file($usedPath)) {
            unlink($usedPath);
        }
    }

    private function hasImage($idProduct)
    {
        $count = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'image`
             WHERE `id_product` = ' . (int) $idProduct
        );

        return (int) $count > 0;
    }

    /**
     * Copy the original and generate every thumbnail size the theme declares.
     */
    private function writeImageFiles(Image $image, $sourcePath)
    {
        $destination = $image->getPathForCreation();

        if (!ImageManager::resize($sourcePath, $destination . '.jpg')) {
            return false;
        }

        foreach (ImageType::getImagesTypes('products') as $imageType) {
            ImageManager::resize(
                $sourcePath,
                $destination . '-' . $imageType['name'] . '.jpg',
                (int) $imageType['width'],
                (int) $imageType['height']
            );
        }

        return true;
    }

    /**
     * The legend is the alt text; reuse the product name so the image is
     * described rather than left anonymous (WCAG 1.1.1).
     */
    private function setLegend(Image $image, $idProduct)
    {
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $product = new Product((int) $idProduct, false, $idLang);

            $image->legend[$idLang] = (string) $product->name;
        }

        $image->update();
    }
}
