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
 * When no photograph exists — which is the case for most room types — it falls
 * back to a generated branded placeholder rather than leaving the product
 * blank or repeating a picture that belongs to something else.
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

    /** @var GFPlaceholderImageGenerator|null Optional: without it, no fallback. */
    private $placeholderGenerator;

    public function __construct(
        GFImageLocator $locator,
        GFPlaceholderImageGenerator $placeholderGenerator = null
    ) {
        $this->locator = $locator;
        $this->placeholderGenerator = $placeholderGenerator;
    }

    /**
     * Give a product its cover image, unless it already has one.
     *
     * Idempotent by design: a product that already carries an image is left
     * alone, so re-importing does not pile up duplicates or discard a
     * photograph uploaded through the back office.
     *
     * @param  int    $idProduct
     * @param  string $fileName Bare file name from the source data; may be
     *                empty, in which case the placeholder is used.
     * @param  GFImagePlaceholder|null $placeholder Drawn when no photograph is
     *                found. Without one, a product with no picture stays bare.
     * @return bool   True when an image was added.
     */
    public function attach($idProduct, $fileName, GFImagePlaceholder $placeholder = null)
    {
        if ($this->hasImage($idProduct)) {
            return false;
        }

        $photograph = $fileName === '' ? null : $this->locator->locate($fileName);
        $drawn = $photograph === null ? $this->draw($placeholder) : null;
        $sourcePath = $photograph !== null ? $photograph : $drawn;

        if ($sourcePath === null) {
            return false;
        }

        $added = $this->addCoverImage($idProduct, $sourcePath);

        if ($drawn !== null && is_file($drawn)) {
            unlink($drawn);
        }

        return $added;
    }

    /**
     * @return string|null Path to a freshly drawn placeholder.
     */
    private function draw(GFImagePlaceholder $placeholder = null)
    {
        if ($this->placeholderGenerator === null || $placeholder === null) {
            return null;
        }

        if (!$placeholder->isDrawable()) {
            return null;
        }

        return $this->placeholderGenerator->generate(
            $placeholder->label,
            $placeholder->variantKey,
            $placeholder->sublabel
        );
    }

    /**
     * Create the ps_image row and its files, rolling the row back when the
     * files cannot be written — an image record with no file on disk shows as
     * a broken picture on every listing.
     *
     * @return bool
     */
    private function addCoverImage($idProduct, $sourcePath)
    {
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
