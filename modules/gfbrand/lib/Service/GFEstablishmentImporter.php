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

    public function __construct(
        GFEstablishmentCsvReader $reader,
        GFEstablishmentProductFactory $factory,
        GFEstablishmentRepository $repository
    ) {
        $this->reader = $reader;
        $this->factory = $factory;
        $this->repository = $repository;
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
        } catch (GFImportException $exception) {
            $result->recordFailure($establishment->name . ': ' . $exception->getMessage());

            return;
        }

        $existingId ? $result->recordUpdated() : $result->recordCreated();
    }

    /**
     * @return int Number of products removed.
     */
    private function deleteImported()
    {
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
