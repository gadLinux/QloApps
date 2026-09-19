<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the hotel source-id migration.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFMigration20260903002HotelSourceId::class)]
class GFMigration20260903002HotelSourceIdTest extends TestCase
{
    /** @var GFMigration20260903002HotelSourceId */
    private $migration;

    protected function setUp(): void
    {
        $this->migration = new GFMigration20260903002HotelSourceId();
    }

    #[Test]
    public function it_sorts_after_the_establishments_migration(): void
    {
        $establishments = new GFMigration20260903001Establishments();

        $this->assertGreaterThan(
            $establishments->getVersion(),
            $this->migration->getVersion()
        );
    }

    #[Test]
    public function it_adds_the_source_column_and_its_index(): void
    {
        $schema = new GFRecordingSchemaHelper(['htl_branch_info']);

        $this->assertTrue($this->migration->up($schema));

        $this->assertSame([
            'addColumn:htl_branch_info.gf_source_id',
            'addIndex:htl_branch_info.gf_source_id',
        ], $schema->getOperationKeys());
    }

    /**
     * The hotel tables belong to hotelreservationsystem. A shop without that
     * module must still migrate cleanly rather than failing the whole run.
     */
    #[Test]
    public function it_does_nothing_when_the_hotel_table_is_absent(): void
    {
        $schema = new GFRecordingSchemaHelper();

        $this->assertTrue($this->migration->up($schema));
        $this->assertSame([], $schema->getOperationKeys());
    }

    #[Test]
    public function running_it_twice_changes_nothing_the_second_time(): void
    {
        $schema = new GFRecordingSchemaHelper([
            'htl_branch_info',
            'htl_branch_info.gf_source_id',
        ]);

        $this->assertTrue($this->migration->up($schema));
        $this->assertSame([], $schema->getOperationKeys());
    }

    #[Test]
    public function down_removes_the_index_and_the_column(): void
    {
        $schema = new GFRecordingSchemaHelper([
            'htl_branch_info',
            'htl_branch_info.gf_source_id',
        ]);

        $this->assertTrue($this->migration->down($schema));

        $this->assertSame([
            'dropIndex:htl_branch_info.gf_source_id',
            'dropColumn:htl_branch_info.gf_source_id',
        ], $schema->getOperationKeys());
    }

    #[Test]
    public function down_tolerates_a_shop_without_the_hotel_module(): void
    {
        $schema = new GFRecordingSchemaHelper();

        $this->assertTrue($this->migration->down($schema));
        $this->assertSame([], $schema->getOperationKeys());
    }
}
