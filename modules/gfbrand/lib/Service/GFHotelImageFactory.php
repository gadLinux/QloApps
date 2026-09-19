<?php
/**
 * 2026 GF Experiences
 *
 * Gives a hotel its own photograph.
 *
 * A hotel's picture is not its room types' picture: QloApps stores it in
 * htl_image, under img/hotels/, at the hotel image sizes. The search results
 * and the hotel header read from there, so a hotel with no htl_image row shows
 * whatever the listing can scrape together — which is how a room type's
 * placeholder ended up standing in for the building.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFHotelImageFactory
{
    /** @var GFImageLocator */
    private $locator;

    /** @var GFReadableImage */
    private $readableImage;

    public function __construct(GFImageLocator $locator, GFReadableImage $readableImage = null)
    {
        $this->locator = $locator;
        $this->readableImage = $readableImage ?: new GFReadableImage();
    }

    /**
     * Attach the establishment's photograph to its hotel, unless it has one.
     *
     * Idempotent like its product counterpart: a hotel that already carries an
     * image keeps it, so re-importing never discards a picture uploaded
     * through the back office.
     *
     * @param  int    $idHotel
     * @param  string $fileName Bare file name from the source data.
     * @return bool   True when an image was added.
     */
    public function attach($idHotel, $fileName)
    {
        if ($fileName === '' || $this->hasImage($idHotel)) {
            return false;
        }

        $sourcePath = $this->locator->locate($fileName);

        if ($sourcePath === null) {
            return false;
        }

        $readablePath = $this->readableImage->pathFor($sourcePath);

        if ($readablePath === null) {
            return false;
        }

        $added = $this->addCoverImage($idHotel, $readablePath);
        $this->readableImage->discard($readablePath, $sourcePath);

        return $added;
    }

    /**
     * @return bool
     */
    private function addCoverImage($idHotel, $readablePath)
    {
        $image = new HotelImage();
        $image->id_hotel = (int) $idHotel;
        $image->cover = 1;

        if (!$image->save()) {
            return false;
        }

        if (!$this->writeImageFiles($image, $readablePath)) {
            $image->delete();

            return false;
        }

        return true;
    }

    /**
     * Copy the original and generate every size declared for hotels.
     */
    private function writeImageFiles(HotelImage $image, $sourcePath)
    {
        $directory = $image->getPathForCreation();

        if (!$directory) {
            return false;
        }

        $base = $directory . $image->id;

        if (!ImageManager::resize($sourcePath, $base . '.' . $image->image_format)) {
            return false;
        }

        foreach (ImageType::getImagesTypes('hotels') as $imageType) {
            ImageManager::resize(
                $sourcePath,
                $base . '-' . $imageType['name'] . '.' . $image->image_format,
                (int) $imageType['width'],
                (int) $imageType['height']
            );
        }

        return true;
    }

    private function hasImage($idHotel)
    {
        $count = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_image`
             WHERE `id_hotel` = ' . (int) $idHotel
        );

        return (int) $count > 0;
    }
}
