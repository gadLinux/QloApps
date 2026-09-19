<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the establishments migration — Story 1.8.
 *
 * Asserts what the migration would change. The DDL itself is covered by the
 * integration suite against the real schema.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFMigration20260903001Establishments::class)]
class GFMigration20260903001EstablishmentsTest extends TestCase
{
    /** @var GFMigration20260903001Establishments */
    private $migration;

    protected function setUp(): void
    {
        $this->migration = new GFMigration20260903001Establishments();
    }

    #[Test]
    public function its_version_sorts_chronologically(): void
    {
        $this->assertSame('20260903_001', $this->migration->getVersion());
        $this->assertNotSame('', $this->migration->getDescription());
    }

    #[Test]
    public function it_adds_every_establishment_column_and_the_filter_index(): void
    {
        $schema = new GFRecordingSchemaHelper();

        $this->assertTrue($this->migration->up($schema));

        $this->assertSame([
            'addColumn:product.gf_source_id',
            'addColumn:product.gf_type',
            'addColumn:product.gf_destination_url',
            'addColumn:product.gf_certification',
            'addColumn:product.gf_country',
            'addColumn:product.gf_city',
            'addColumn:product.gf_has_channel_manager',
            'addColumn:product.gf_channel_manager_status',
            'addColumn:product.gf_featured_home',
            'addIndex:product.gf_type_country',
        ], $schema->getOperationKeys());
    }

    /**
     * The runner calls up() on every load once a version is pending, and a
     * half-applied schema must not make it fail.
     */
    #[Test]
    public function it_skips_columns_that_already_exist(): void
    {
        $schema = new GFRecordingSchemaHelper([
            'product.gf_source_id',
            'product.gf_type',
        ]);

        $this->assertTrue($this->migration->up($schema));

        $keys = $schema->getOperationKeys();
        $this->assertNotContains('addColumn:product.gf_source_id', $keys);
        $this->assertNotContains('addColumn:product.gf_type', $keys);
        $this->assertContains('addColumn:product.gf_country', $keys);
    }

    #[Test]
    public function running_it_twice_changes_nothing_the_second_time(): void
    {
        $applied = [
            'product.gf_source_id', 'product.gf_type', 'product.gf_destination_url',
            'product.gf_certification', 'product.gf_country', 'product.gf_city',
            'product.gf_has_channel_manager', 'product.gf_channel_manager_status',
            'product.gf_featured_home', 'product.gf_type_country',
        ];
        $schema = new GFRecordingSchemaHelper($applied);

        $this->assertTrue($this->migration->up($schema));
        $this->assertSame([], $schema->getOperationKeys());
    }

    #[Test]
    public function it_stops_when_a_column_cannot_be_added(): void
    {
        $schema = new GFRecordingSchemaHelper([], 'product.gf_country');

        $this->assertFalse($this->migration->up($schema));

        // It gave up rather than pressing on to the index.
        $this->assertNotContains('addIndex:product.gf_type_country', $schema->getOperationKeys());
    }

    #[Test]
    public function down_removes_the_index_before_the_columns_it_covers(): void
    {
        $existing = [
            'product.gf_source_id', 'product.gf_type', 'product.gf_destination_url',
            'product.gf_certification', 'product.gf_country', 'product.gf_city',
            'product.gf_has_channel_manager', 'product.gf_channel_manager_status',
            'product.gf_featured_home', 'product.gf_type_country',
        ];
        $schema = new GFRecordingSchemaHelper($existing);

        $this->assertTrue($this->migration->down($schema));

        $keys = $schema->getOperationKeys();
        $this->assertSame('dropIndex:product.gf_type_country', $keys[0]);
        $this->assertContains('dropColumn:product.gf_source_id', $keys);
        $this->assertCount(10, $keys);
    }

    #[Test]
    public function down_tolerates_a_schema_that_was_never_migrated(): void
    {
        $schema = new GFRecordingSchemaHelper();

        $this->assertTrue($this->migration->down($schema));
        $this->assertSame([], $schema->getOperationKeys());
    }
}
