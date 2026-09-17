<?php
/**
 * 2026 GF Experiences
 *
 * Integration tests for the advisor/partner import — story 1.11, AC-1/AC-2.
 *
 * Runs the real migration DDL and the real multilang ObjectModels against the
 * live schema. The reloadability rule is the point: importing twice must not
 * duplicate, and a reload must not touch records a human created in the back
 * office (those carry no source id).
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFAdvisorPartnerImporter::class)]
class GFAdvisorPartnerImporterTest extends TestCase
{
    /**
     * Fixture source ids carry this prefix, and cleanup is scoped to it. The
     * real seed (source ids 1-4) is left untouched.
     */
    const FIXTURE_PREFIX = 'test-';

    /** @var GFAdvisorPartnerImporter */
    private $importer;

    /** @var string */
    private $advisorsFixture;

    /** @var string */
    private $partnersFixture;

    public static function setUpBeforeClass(): void
    {
        $migration = new GFMigration20260909001AdvisorsPartners();
        $migration->up(new GFSchemaHelper());
    }

    protected function setUp(): void
    {
        $this->importer = new GFAdvisorPartnerImporter(new GFAdvisorPartnerCsvReader());
        $this->advisorsFixture = dirname(__DIR__) . '/fixtures/advisors-test.csv';
        $this->partnersFixture = dirname(__DIR__) . '/fixtures/partners-test.csv';

        $this->deleteTestRows();
    }

    protected function tearDown(): void
    {
        $this->deleteTestRows();
    }

    #[Test]
    public function it_creates_a_record_for_every_row(): void
    {
        $result = $this->importer->importAdvisors($this->advisorsFixture);

        $this->assertTrue($result->isSuccessful(), implode('; ', $result->getErrors()));
        $this->assertSame(2, $result->getCreated());
        $this->assertSame(0, $result->getUpdated());
        $this->assertSame(2, $this->countTestAdvisors());
    }

    #[Test]
    public function it_writes_the_multilang_name_to_every_language(): void
    {
        $this->importer->importAdvisors($this->advisorsFixture);

        $id = $this->advisorIdFor('test-1');
        $this->assertNotSame(0, $id);

        $languages = Language::getLanguages(false);
        $this->assertGreaterThan(0, count($languages));

        foreach ($languages as $language) {
            $advisor = new GfAdvisor($id, (int) $language['id_lang']);
            $this->assertTrue(Validate::isLoadedObject($advisor));
            $this->assertSame('Test Advisor One', (string) $advisor->name);
        }
    }

    #[Test]
    public function importing_twice_updates_rather_than_duplicates(): void
    {
        $first = $this->importer->importAdvisors($this->advisorsFixture);
        $idAfterFirst = $this->advisorIdFor('test-1');

        $second = $this->importer->importAdvisors($this->advisorsFixture);

        $this->assertSame(2, $first->getCreated());
        $this->assertSame(0, $second->getCreated());
        $this->assertSame(2, $second->getUpdated());
        $this->assertSame(2, $this->countTestAdvisors());
        $this->assertSame($idAfterFirst, $this->advisorIdFor('test-1'));
    }

    #[Test]
    public function it_creates_partners_and_reads_them_back_in_order(): void
    {
        $result = $this->importer->importPartners($this->partnersFixture);

        $this->assertTrue($result->isSuccessful(), implode('; ', $result->getErrors()));
        $this->assertSame(2, $result->getCreated());

        $listing = GFAdvisorPartnerListing::forLanguage((int) Configuration::get('PS_LANG_DEFAULT'));
        $partners = $listing->partnerCards();

        $testPartners = array_values(array_filter($partners, function ($card) {
            return in_array($card['name'], ['Test Partner One', 'Test Partner Two'], true);
        }));

        $this->assertCount(2, $testPartners);
        $this->assertSame('Test Partner One', $testPartners[0]['name']);
        $this->assertSame('Test Partner Two', $testPartners[1]['name']);
        $this->assertTrue($testPartners[0]['has_logo']);
    }

    /**
     * A record created by hand has no source id, which is exactly what
     * protects it from a reload.
     *
     * reload() deletes every row this importer has ever created, tracked
     * only by source id, with no awareness of which file's fixtures those
     * are — so against a database that already carries the real seed
     * (source ids 1-4), calling the real reload() here would delete it and
     * never recreate it, since this test only re-imports the test fixtures.
     * The snapshot/restore around the call is what makes this test safe to
     * run against a real dev database instead of quietly destroying it.
     */
    #[Test]
    public function a_reload_leaves_hand_made_records_alone(): void
    {
        // Snapshot BEFORE creating the hand-made record: the restore below
        // must bring the tables back to what they held before this test
        // touched anything, not back to "hand-made record already added".
        $snapshot = $this->snapshotAdvisorPartnerTables();

        $manual = new GfAdvisor();
        $manual->name = array_fill_keys(Language::getIDs(false), 'Hand-made advisor');
        $manual->active = 1;
        $manual->add();
        // ObjectModel 1.6 stores the new id in the generic $this->id.
        $manualId = (int) $manual->id;

        try {
            $this->importer->importAdvisors($this->advisorsFixture);

            $result = $this->importer->reload($this->advisorsFixture, $this->partnersFixture);

            $this->assertTrue(Validate::isLoadedObject(new GfAdvisor($manualId)));
            $this->assertSame(2, $this->countTestAdvisors());
            $this->assertSame(2, $this->countTestPartners());
            // Pins reload() as actually destructive-then-recreating, not a
            // no-op upsert a broken deleteImported() could pass as too.
            $this->assertGreaterThan(0, $result->getDeleted());
        } finally {
            $this->restoreAdvisorPartnerTables($snapshot);
        }
    }

    #[Test]
    public function an_unreadable_source_file_fails_without_writing(): void
    {
        $result = $this->importer->importAdvisors('/nonexistent/advisors.csv');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(0, $this->countTestAdvisors());
        $this->assertStringContainsString('CSV not found', $result->getErrors()[0]);
    }

    private function advisorIdFor($sourceId)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT `id_gf_advisor` FROM `' . _DB_PREFIX_ . 'gf_advisor_source`
             WHERE `source_id` = \'' . pSQL($sourceId) . '\''
        );
    }

    private function countTestAdvisors()
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'gf_advisor_source`
             WHERE `source_id` LIKE \'' . pSQL(self::FIXTURE_PREFIX) . '%\''
        );
    }

    private function countTestPartners()
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'gf_partner_source`
             WHERE `source_id` LIKE \'' . pSQL(self::FIXTURE_PREFIX) . '%\''
        );
    }

    /**
     * @return array<string, array<int, array<string, mixed>>> One row set per
     * table, keyed by table name without the DB prefix.
     */
    private function snapshotAdvisorPartnerTables(): array
    {
        $tables = ['gf_advisor', 'gf_advisor_lang', 'gf_advisor_source', 'gf_partner', 'gf_partner_lang', 'gf_partner_source'];
        $snapshot = [];

        foreach ($tables as $table) {
            $snapshot[$table] = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . $table . '`') ?: [];
        }

        return $snapshot;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $snapshot
     */
    private function restoreAdvisorPartnerTables(array $snapshot): void
    {
        // Children first when clearing (lang/source reference the base
        // table), parents first when restoring.
        $order = ['gf_advisor_lang', 'gf_advisor_source', 'gf_advisor', 'gf_partner_lang', 'gf_partner_source', 'gf_partner'];

        foreach ($order as $table) {
            Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . $table . '`');
        }

        foreach (array_reverse($order) as $table) {
            foreach ($snapshot[$table] as $row) {
                $columns = array_map(function ($column) {
                    return '`' . bqSQL($column) . '`';
                }, array_keys($row));
                $values = array_map(function ($value) {
                    return $value === null ? 'NULL' : '\'' . pSQL($value) . '\'';
                }, array_values($row));

                Db::getInstance()->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . $table . '` (' . implode(', ', $columns) . ')
                     VALUES (' . implode(', ', $values) . ')'
                );
            }
        }
    }

    private function deleteTestRows(): void
    {
        foreach (['advisor', 'partner'] as $kind) {
            $table = $kind === 'advisor' ? 'gf_advisor' : 'gf_partner';
            $sourceTable = $table . '_source';
            $idColumn = 'id_' . $table;

            $rows = Db::getInstance()->executeS(
                'SELECT `' . $idColumn . '` FROM `' . _DB_PREFIX_ . $sourceTable . '`
                 WHERE `source_id` LIKE \'' . pSQL(self::FIXTURE_PREFIX) . '%\''
            );

            if (!$rows) {
                continue;
            }

            $ids = array_map(function ($row) use ($idColumn) {
                return (int) $row[$idColumn];
            }, $rows);
            $idList = implode(', ', $ids);

            Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . $table . '` WHERE `' . $idColumn . '` IN (' . $idList . ')');
            Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . $table . '_lang` WHERE `' . $idColumn . '` IN (' . $idList . ')');
            Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . $sourceTable . '` WHERE `' . $idColumn . '` IN (' . $idList . ')');
        }
    }
}
