<?php
/**
 * 2026 GF Experiences
 *
 * Loads establishments from a source file into the catalogue — Story 1.8.
 *
 * Two modes:
 *   import()  upsert. Rows are matched on their source id, so running it again
 *             updates what it created before instead of duplicating it, and
 *             back-office edits to other fields survive.
 *   reload()  delete every establishment this importer created, then import.
 *             Products created by hand have no source id and are left alone.
 *
 * APPLICATION LAYER — orchestrates the reader, the factory and the repository.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFEstablishmentImporter
{
    /** @var GFEstablishmentCsvReader */
    private $reader;

    /** @var GFEstablishmentProductFactory */
    private $factory;

    /** @var GFEstablishmentRepository */
    private $repository;

    /**
     * @var GFHotelProvisioner|null Optional: when absent, hotels are imported
     *      as informational products only, which is what a shop without
     *      hotelreservationsystem can support.
     */
    private $hotelProvisioner;

    /** @var GFProductImageFactory|null Optional: without it, no photographs. */
    private $imageFactory;

    public function __construct(
        GFEstablishmentCsvReader $reader,
        GFEstablishmentProductFactory $factory,
        GFEstablishmentRepository $repository,
        GFHotelProvisioner $hotelProvisioner = null,
        GFProductImageFactory $imageFactory = null
    ) {
        $this->reader = $reader;
        $this->factory = $factory;
        $this->repository = $repository;
        $this->hotelProvisioner = $hotelProvisioner;
        $this->imageFactory = $imageFactory;
    }

    /**
     * Create what is missing, update what is already there.
     *
     * @param  string $path Absolute path to the source file.
     * @return GFImportResult
     */
    public function import($path)
    {
        $result = new GFImportResult();

        try {
            $establishments = $this->reader->read($path);
        } catch (GFImportException $exception) {
            $result->recordFailure($exception->getMessage());

            return $result;
        }

        $result->addErrors($this->reader->getErrors());

        foreach ($establishments as $establishment) {
            $this->save($establishment, $result);
        }

        return $result;
    }

    /**
     * Discard the imported establishments and load the file from scratch.
     *
     * @param  string $path
     * @return GFImportResult
     */
    public function reload($path)
    {
        $deleted = $this->deleteImported();

        $result = $this->import($path);
        $result->recordDeleted($deleted);

        return $result;
    }

    /**
     * Report what an import would do, without writing anything.
     *
     * @param  string $path
     * @return GFImportResult
     */
    public function preview($path)
    {
        $result = new GFImportResult();

        try {
            $establishments = $this->reader->read($path);
        } catch (GFImportException $exception) {
            $result->recordFailure($exception->getMessage());

            return $result;
        }

        $result->addErrors($this->reader->getErrors());

        foreach ($establishments as $establishment) {
            $this->repository->findIdBySourceId($establishment->sourceId)
                ? $result->recordUpdated()
                : $result->recordCreated();
        }

        return $result;
    }

    private function save(GFEstablishment $establishment, GFImportResult $result)
    {
        $existingId = $this->repository->findIdBySourceId($establishment->sourceId);

        try {
            $product = $this->factory->persist($establishment, $existingId);

            if (!$this->repository->saveFields((int) $product->id, $establishment)) {
                throw new GFImportException('Could not write GF fields');
            }

            $this->attachImage((int) $product->id, $establishment);
            $this->provisionHotel($establishment);
        } catch (GFImportException $exception) {
            $result->recordFailure($establishment->name . ': ' . $exception->getMessage());

            return;
        }

        $existingId ? $result->recordUpdated() : $result->recordCreated();
    }

    /**
     * A missing photograph is not a failed import — the establishment falls
     * back to a branded placeholder and is still correct.
     */
    private function attachImage($idProduct, GFEstablishment $establishment)
    {
        if ($this->imageFactory === null) {
            return;
        }

        $placeholder = new GFImagePlaceholder(
            $establishment->name,
            $establishment->sourceId,
            $establishment->city
        );

        $this->imageFactory->attach($idProduct, $establishment->imageFile, $placeholder);
    }

    /**
     * A hotel gets a bookable structure on top of its product row. A
     * restaurant or experience links out to its own site and needs none.
     */
    private function provisionHotel(GFEstablishment $establishment)
    {
        if ($this->hotelProvisioner === null) {
            return;
        }

        if (!$this->hotelProvisioner->supports($establishment)) {
            return;
        }

        $this->hotelProvisioner->provision($establishment);
    }

    /**
     * @return int Number of products removed.
     */
    private function deleteImported()
    {
        // Hotels first: deleting a hotel branch cleans up its rooms and links,
        // which would otherwise be left pointing at deleted products.
        if ($this->hotelProvisioner !== null) {
            $this->hotelProvisioner->deleteImportedHotels();
        }

        $deleted = 0;

        foreach ($this->repository->findImportedIds() as $idProduct) {
            $product = new Product($idProduct);

            if (Validate::isLoadedObject($product) && $product->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
