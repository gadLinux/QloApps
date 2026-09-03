<?php
/**
 * 2026 GF Experiences
 *
 * Integration tests for the schema helper and the establishments migration.
 *
 * These run the real DDL against the live database, on a scratch table so the
 * product catalogue is never touched.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFSchemaHelper::class)]
class GFSchemaMigrationTest extends TestCase
{
    /** Scratch table, created and dropped per test. */
    const TABLE = 'gf_schema_probe';

    /** @var GFSchemaHelper */
    private $schema;

    protected function setUp(): void
    {
        $this->schema = new GFSchemaHelper();
        $this->schema->dropTable(self::TABLE);
        $this->schema->createTable(
            self::TABLE,
            '`id` INT(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)'
        );
        $this->schema->clearLog();
    }

    protected function tearDown(): void
    {
        $this->schema->dropTable(self::TABLE);
    }

    #[Test]
    public function it_creates_and_detects_a_table(): void
    {
        $this->assertTrue($this->schema->tableExists(self::TABLE));
        $this->assertFalse($this->schema->tableExists('gf_table_that_is_not_there'));
    }

    #[Test]
    public function it_adds_a_column_that_mysql_then_reports(): void
    {
        $this->assertFalse($this->schema->columnExists(self::TABLE, 'gf_country'));

        $this->assertTrue($this->schema->addColumn(self::TABLE, 'gf_country', 'VARCHAR(100) NULL'));

        $this->assertTrue($this->schema->columnExists(self::TABLE, 'gf_country'));
        $this->assertSame(['+ column ' . self::TABLE . '.gf_country'], $this->schema->getLog());
    }

    /**
     * The property the migration runner depends on: re-running must be a
     * silent no-op, not an error.
     */
    #[Test]
    public function adding_the_same_column_twice_is_a_no_op(): void
    {
        $this->schema->addColumn(self::TABLE, 'gf_country', 'VARCHAR(100) NULL');
        $this->schema->clearLog();

        $this->assertTrue($this->schema->addColumn(self::TABLE, 'gf_country', 'VARCHAR(100) NULL'));
        $this->assertSame([], $this->schema->getLog());
    }

    #[Test]
    public function it_drops_a_column_and_ignores_one_that_is_gone(): void
    {
        $this->schema->addColumn(self::TABLE, 'gf_city', 'VARCHAR(200) NULL');

        $this->assertTrue($this->schema->dropColumn(self::TABLE, 'gf_city'));
        $this->assertFalse($this->schema->columnExists(self::TABLE, 'gf_city'));

        $this->schema->clearLog();
        $this->assertTrue($this->schema->dropColumn(self::TABLE, 'gf_city'));
        $this->assertSame([], $this->schema->getLog());
    }

    #[Test]
    public function it_manages_a_composite_index(): void
    {
        $this->schema->addColumn(self::TABLE, 'gf_type', "ENUM('HOTEL','RESTAURANT') NULL");
        $this->schema->addColumn(self::TABLE, 'gf_country', 'VARCHAR(100) NULL');

        $this->assertFalse($this->schema->indexExists(self::TABLE, 'gf_type_country'));

        $this->assertTrue(
            $this->schema->addIndex(self::TABLE, 'gf_type_country', ['gf_type', 'gf_country'])
        );
        $this->assertTrue($this->schema->indexExists(self::TABLE, 'gf_type_country'));

        $this->assertTrue($this->schema->dropIndex(self::TABLE, 'gf_type_country'));
        $this->assertFalse($this->schema->indexExists(self::TABLE, 'gf_type_country'));
    }

    #[Test]
    public function an_enum_column_keeps_the_definition_it_was_given(): void
    {
        $this->schema->addColumn(
            self::TABLE,
            'gf_channel_manager_status',
            "ENUM('not_connected','active','inactive') NOT NULL DEFAULT 'not_connected'"
        );

        // executeS(), not getRow(): getRow() appends LIMIT 1, which SHOW rejects.
        $columns = Db::getInstance()->executeS(
            'SHOW COLUMNS FROM `' . _DB_PREFIX_ . self::TABLE . '` LIKE \'gf_channel_manager_status\''
        );

        $this->assertCount(1, $columns);
        $this->assertStringContainsString('not_connected', $columns[0]['Type']);
        $this->assertSame('not_connected', $columns[0]['Default']);
    }
}
