<?php
/**
 * 2026 GF Experiences
 *
 * Integration tests for the establishment import — Story 1.8.
 *
 * Exercises the real Product model, the real schema and the real repository.
 * The reloadability requirement is the point of this file: importing twice
 * must not duplicate, and a reload must not touch hand-made products.
 *
 * CONFIRMED ROOT CAUSE of the "silent establishment-catalogue re-import"
 * story 1.12 flagged and story 1.13 could only reproduce, never explain:
 * it was never gfbrand's install()/uninstall() lifecycle at all.
 * `a_reload_deletes_the_previous_import_and_starts_again()` and
 * `a_reload_leaves_hand_made_products_alone()` used to call
 * clearEntireImportedCatalogue(), which deletes EVERY row with a
 * `gf_source_id` — not just this file's `test-`-prefixed fixtures, the real
 * seeded catalogue too — then relied on tearDownAfterClass() re-importing
 * from the real CSV to "restore" it. A fresh CSV import is a fresh INSERT:
 * every restored establishment got a brand-new `id_product`. Any developer
 * who ran `make test` and then redeployed watched the whole catalogue's ids
 * shift, with no code change of their own to blame.
 *
 * Fixed by never leaving a trace of touching the real catalogue at all:
 * both tests now run inside a transaction that is always rolled back
 * (restores the exact original rows, same ids, same everything — no CSV
 * re-import, no new ids, ever) AND a filesystem snapshot/restore of the
 * product image directory around it, since Product::delete()'s image
 * cleanup is not transactional and a DB rollback alone left every real
 * establishment's photo 404ing despite its ps_image row being back. See
 * runAndRollBack().
 *
 * A second, related bug found alongside this one: GFEstablishmentRepository
 * ::saveFields() used to write `gf_featured_home` from
 * GFEstablishment::$featuredHome on every import() upsert — a property the
 * CSV reader never populates, so it was always false. A plain re-import
 * (this test file's own repair, or a real operator's "reload" from the
 * back office) silently reset every admin's featured-strip pick. Fixed by
 * removing that column from saveFields() entirely — it is owned
 * exclusively by setFeaturedHome()/setFeaturedHomeByName(). See
 * reimporting_never_touches_an_existing_featured_flag().
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

    public static function setUpBeforeClass(): void
    {
        // The columns must exist before any of this can run.
        $migration = new GFMigration20260903001Establishments();
        $migration->up(new GFSchemaHelper());
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

    /**
     * Confirmed live: a plain re-import against the real catalogue reset
     * every admin's featured-strip pick back to unfeatured, because
     * saveFields() used to write gf_featured_home from
     * GFEstablishment::$featuredHome — a property the CSV reader never
     * populates, so it is always false. That flag belongs exclusively to
     * setFeaturedHome()/setFeaturedHomeByName(); the import path must never
     * touch it, on create or on update.
     */
    #[Test]
    public function reimporting_never_touches_an_existing_featured_flag(): void
    {
        // setFeaturedHome()'s own contract is catalogue-wide ("exactly these
        // ids are featured, unset everyone else" -- see its docblock), not
        // scoped to this file's test- fixtures. Calling it at all, even with
        // only a fixture id, unsets every *real* establishment's flag as a
        // side effect. Confirmed live: this test originally did exactly
        // that and silently cleared the real seeded catalogue's featured
        // strip -- TWICE, since a naive outer DB transaction does not help
        // either: setFeaturedHome() issues its own internal START
        // TRANSACTION/COMMIT (see its implementation), which implicitly
        // commits whatever outer transaction was already open before this
        // test's own rollback ever runs. Capture-and-restore by id, the
        // same pattern GFFeaturedHomeDefaultSeedTest's setUp()/tearDown()
        // already use for the same reason, is the only thing that works.
        $realFeaturedIds = array_column(
            array_filter(
                $this->repository->findAllForFeaturedPicker((int) Configuration::get('PS_LANG_DEFAULT')),
                function ($row) {
                    return (int) $row['gf_featured_home'] === 1;
                }
            ),
            'id_product'
        );

        try {
            $this->importer->import($this->fixture);
            $idProduct = $this->repository->findIdBySourceId('test-1');
            $this->repository->setFeaturedHome([$idProduct]);

            $this->importer->import($this->fixture);

            $this->assertSame(
                1,
                (int) Db::getInstance()->getValue(
                    'SELECT `gf_featured_home` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . (int) $idProduct
                ),
                'Re-importing an establishment must never reset its featured flag.'
            );
        } finally {
            $this->repository->setFeaturedHome($realFeaturedIds);
        }
    }

    #[Test]
    public function a_reload_deletes_the_previous_import_and_starts_again(): void
    {
        // reload() clears everything the importer owns, real seeded
        // catalogue included — see this file's class docblock. Rolled back
        // unconditionally, so the real catalogue is restored byte-for-byte,
        // never through a CSV re-import that would hand it new ids.
        $this->runAndRollBack(function () {
            $this->clearEntireImportedCatalogue();

            $this->importer->import($this->fixture);
            $idBefore = $this->repository->findIdBySourceId('test-1');

            $result = $this->importer->reload($this->fixture);

            $this->assertSame(2, $result->getDeleted());
            $this->assertSame(2, $result->getCreated());
            $this->assertSame(0, $result->getUpdated());
            $this->assertSame(2, $this->countFixtures());
            $this->assertNotSame($idBefore, $this->repository->findIdBySourceId('test-1'));
        });
    }

    /**
     * A reload must never delete a product a human created in the back office.
     * Those carry no source id, which is exactly what protects them.
     */
    #[Test]
    public function a_reload_leaves_hand_made_products_alone(): void
    {
        $this->runAndRollBack(function () {
            $this->clearEntireImportedCatalogue();

            $manualId = $this->createManualProduct('Hand-made room type');
            $this->importer->import($this->fixture);

            $this->importer->reload($this->fixture);

            $survivor = new Product($manualId);
            $this->assertTrue(Validate::isLoadedObject($survivor));
            $this->assertSame(2, $this->countFixtures());

            // Belongs to this transaction only -- rolling back already
            // undoes it, and tearDown()'s own manualProductIds cleanup runs
            // outside this transaction, after the rollback already removed it.
            $this->manualProductIds = [];
        });
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
     * Run $test inside a transaction that is always rolled back, success or
     * failure -- the only way to test a delete-everything-then-reimport
     * operation against the real, shared, live catalogue without leaving a
     * trace. A rollback restores the exact original rows (same id_product,
     * same everything); nothing here ever re-inserts via CSV.
     */
    private function runAndRollBack(callable $test): void
    {
        // A DB rollback restores rows, but Product::delete()/Image writes
        // touch the filesystem directly -- that is never transactional and
        // a ROLLBACK does not undo it. Confirmed live: without this, the
        // real establishments' photos were deleted from disk by
        // clearEntireImportedCatalogue() and never came back, even though
        // the ROLLBACK correctly restored every ps_image row pointing at
        // them -- a 404 on every card, not visible from the DB at all.
        $imageDir = rtrim(_PS_PROD_IMG_DIR_, '/');
        $backupDir = sys_get_temp_dir() . '/gf-test-img-backup-' . getmypid();

        exec('rm -rf ' . escapeshellarg($backupDir) . ' && cp -r ' . escapeshellarg($imageDir) . ' ' . escapeshellarg($backupDir));

        Db::getInstance()->execute('START TRANSACTION');

        try {
            $test();
        } finally {
            Db::getInstance()->execute('ROLLBACK');
            exec('rm -rf ' . escapeshellarg($imageDir) . ' && mv ' . escapeshellarg($backupDir) . ' ' . escapeshellarg($imageDir));
        }
    }

    /**
     * Empty the whole importer-owned catalogue, for the reload tests that
     * cannot be scoped. Only ever called inside runAndRollBack().
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
