<?php
/**
 * 2026 GF Experiences
 *
 * Integration tests for the establishment import — Story 1.8.
 *
 * Exercises the real Product model, the real schema and the real repository.
 * The reloadability requirement is the point of this file: importing twice
 * must not duplicate, and a reload must not touch hand-made products.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFEstablishmentImporter::class)]
class GFEstablishmentImporterTest extends TestCase
{
    /**
     * Fixture source ids carry this prefix, and cleanup is scoped to it.
     *
     * Without that scope these tests would delete the real seeded catalogue,
     * which shares the gf_source_id mechanism — a test suite must not destroy
     * the data the developer is working with.
     */
    const FIXTURE_PREFIX = 'test-';

    /** @var GFEstablishmentImporter */
    private $importer;

    /** @var GFEstablishmentRepository */
    private $repository;

    /** @var string */
    private $fixture;

    /** @var int[] Products this test created by hand, to clean up. */
    private $manualProductIds = [];

    /** @var bool Whether the shop had a seeded catalogue before this class ran. */
    private static $hadSeededCatalogue = false;

    public static function setUpBeforeClass(): void
    {
        // The columns must exist before any of this can run.
        $migration = new GFMigration20260903001Establishments();
        $migration->up(new GFSchemaHelper());

        // reload() deletes every importer-owned row by design, so testing it
        // faithfully means clearing the seeded catalogue. Remember whether it
        // was there so tearDownAfterClass can put it back.
        self::$hadSeededCatalogue = (new GFEstablishmentRepository())->countAll() > 0;
    }

    /**
     * Restore the seeded catalogue the reload tests had to clear.
     */
    public static function tearDownAfterClass(): void
    {
        if (!self::$hadSeededCatalogue) {
            return;
        }

        $services = new GFModuleServices(dirname(dirname(__DIR__)));
        $csvPath = $services->getEstablishmentsCsvPath();

        if (file_exists($csvPath)) {
            $services->getEstablishmentImporter()->import($csvPath);
        }
    }

    protected function setUp(): void
    {
        $this->repository = new GFEstablishmentRepository();
        $this->importer = new GFEstablishmentImporter(
            new GFEstablishmentCsvReader(),
            new GFEstablishmentProductFactory(),
            $this->repository
        );
        $this->fixture = dirname(__DIR__) . '/fixtures/establishments-clean.csv';

        $this->deleteImportedProducts();
    }

    protected function tearDown(): void
    {
        $this->deleteImportedProducts();

        foreach ($this->manualProductIds as $id) {
            $product = new Product($id);
            if (Validate::isLoadedObject($product)) {
                $product->delete();
            }
        }

        $this->manualProductIds = [];
    }

    #[Test]
    public function it_creates_a_product_for_every_row(): void
    {
        $result = $this->importer->import($this->fixture);

        $this->assertTrue($result->isSuccessful(), implode('; ', $result->getErrors()));
        $this->assertSame(2, $result->getCreated());
        $this->assertSame(0, $result->getUpdated());
        $this->assertSame(2, $this->countFixtures());
    }

    #[Test]
    public function it_writes_the_gf_fields_onto_the_product_row(): void
    {
        $this->importer->import($this->fixture);

        $row = $this->fetchImportedRow('test-2');

        $this->assertSame('Hotel Chateau Louis', $this->productName((int) $row['id_product']));
        $this->assertSame('HOTEL', $row['gf_type']);
        $this->assertSame('Canada', $row['gf_country']);
        $this->assertSame('Edmonton AB', $row['gf_city']);
        $this->assertSame('https://www.chateaulouis.com', $row['gf_destination_url']);
        $this->assertSame('options', $row['gf_certification']);
        $this->assertSame('not_connected', $row['gf_channel_manager_status']);

        // Loose comparison: the PDO driver returns native ints for TINYINT
        // on PHP 8.4 but strings on older builds, and that is not what this
        // test is about.
        $this->assertEquals(0, $row['gf_has_channel_manager']);
    }

    /**
     * The reloadable requirement: running the import again updates in place.
     */
    #[Test]
    public function importing_twice_updates_rather_than_duplicates(): void
    {
        $first = $this->importer->import($this->fixture);
        $idAfterFirst = $this->repository->findIdBySourceId('test-1');

        $second = $this->importer->import($this->fixture);

        $this->assertSame(2, $first->getCreated());
        $this->assertSame(0, $second->getCreated());
        $this->assertSame(2, $second->getUpdated());
        $this->assertSame(2, $this->countFixtures());
        $this->assertSame($idAfterFirst, $this->repository->findIdBySourceId('test-1'));
    }

    #[Test]
    public function a_reload_deletes_the_previous_import_and_starts_again(): void
    {
        // reload() clears everything the importer owns, so start from a known
        // empty catalogue; tearDownAfterClass re-seeds it.
        $this->clearEntireImportedCatalogue();

        $this->importer->import($this->fixture);
        $idBefore = $this->repository->findIdBySourceId('test-1');

        $result = $this->importer->reload($this->fixture);

        $this->assertSame(2, $result->getDeleted());
        $this->assertSame(2, $result->getCreated());
        $this->assertSame(0, $result->getUpdated());
        $this->assertSame(2, $this->countFixtures());
        $this->assertNotSame($idBefore, $this->repository->findIdBySourceId('test-1'));
    }

    /**
     * A reload must never delete a product a human created in the back office.
     * Those carry no source id, which is exactly what protects them.
     */
    #[Test]
    public function a_reload_leaves_hand_made_products_alone(): void
    {
        $this->clearEntireImportedCatalogue();

        $manualId = $this->createManualProduct('Hand-made room type');
        $this->importer->import($this->fixture);

        $this->importer->reload($this->fixture);

        $survivor = new Product($manualId);
        $this->assertTrue(Validate::isLoadedObject($survivor));
        $this->assertSame(2, $this->countFixtures());
    }

    /**
     * countByType() counts the whole catalogue, so assert on the delta rather
     * than absolute numbers — a seeded shop would otherwise change the answer.
     */
    #[Test]
    public function it_counts_establishments_by_type(): void
    {
        $before = $this->repository->countByType();

        $this->importer->import($this->fixture);
        $after = $this->repository->countByType();

        $this->assertSame(1, $this->delta($before, $after, 'HOTEL'));
        $this->assertSame(1, $this->delta($before, $after, 'RESTAURANT'));
    }

    /**
     * preview() is what the admin panel calls to say what a run would do.
     */
    #[Test]
    public function preview_reports_the_plan_without_writing(): void
    {
        $preview = $this->importer->preview($this->fixture);

        $this->assertSame(2, $preview->getCreated());
        $this->assertSame(0, $this->countFixtures());

        $this->importer->import($this->fixture);
        $secondPreview = $this->importer->preview($this->fixture);

        $this->assertSame(0, $secondPreview->getCreated());
        $this->assertSame(2, $secondPreview->getUpdated());
    }

    #[Test]
    public function an_unreadable_source_file_fails_without_touching_the_catalogue(): void
    {
        $result = $this->importer->import('/nonexistent/establishments.csv');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(0, $this->countFixtures());
        $this->assertStringContainsString('CSV not found', $result->getErrors()[0]);
    }

    #[Test]
    public function the_migration_has_run_so_the_schema_is_ready(): void
    {
        $this->assertTrue($this->repository->isSchemaReady());
    }

    private function fetchImportedRow(string $sourceId): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'product`
             WHERE `gf_source_id` = \'' . pSQL($sourceId) . '\''
        );

        $this->assertCount(1, $rows, 'Expected exactly one row for source id ' . $sourceId);

        return $rows[0];
    }

    private function productName(int $idProduct): string
    {
        $product = new Product($idProduct, false, (int) Configuration::get('PS_LANG_DEFAULT'));

        return (string) $product->name;
    }

    private function createManualProduct(string $name): int
    {
        $product = new Product();

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $product->name[$idLang] = $name;
            $product->link_rewrite[$idLang] = Tools::link_rewrite($name);
        }

        $product->id_category_default = (int) Configuration::get('PS_HOME_CATEGORY');
        $product->price = 0;
        $product->active = 1;
        $product->save();

        $this->manualProductIds[] = (int) $product->id;

        return (int) $product->id;
    }

    /**
     * Remove only the products these tests imported, leaving the real
     * catalogue — which uses bare numeric source ids — untouched.
     */
    private function deleteImportedProducts(): void
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product`
             WHERE `gf_source_id` LIKE \'' . pSQL(self::FIXTURE_PREFIX) . '%\''
        );

        foreach ((array) $rows as $row) {
            $product = new Product((int) $row['id_product']);
            if (Validate::isLoadedObject($product)) {
                $product->delete();
            }
        }
    }

    /**
     * How many fixture establishments are currently loaded. Scoped like the
     * cleanup, so a populated catalogue does not change the assertions.
     */
    private function countFixtures(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product`
             WHERE `gf_source_id` LIKE \'' . pSQL(self::FIXTURE_PREFIX) . '%\''
        );
    }

    /**
     * Empty the whole importer-owned catalogue, for the reload tests that
     * cannot be scoped. tearDownAfterClass re-seeds what was there.
     */
    private function clearEntireImportedCatalogue(): void
    {
        foreach ($this->repository->findImportedIds() as $id) {
            $product = new Product($id);
            if (Validate::isLoadedObject($product)) {
                $product->delete();
            }
        }
    }

    /**
     * Change in the count for one type between two countByType() snapshots.
     */
    private function delta(array $before, array $after, string $type): int
    {
        return (int) ($after[$type] ?? 0) - (int) ($before[$type] ?? 0);
    }
}
