<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the advisor/partner schema migration — story 1.11, AC-1.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFMigration20260909001AdvisorsPartners::class)]
class GFMigration20260909001AdvisorsPartnersTest extends TestCase
{
    /** @var GFMigration20260909001AdvisorsPartners */
    private $migration;

    protected function setUp(): void
    {
        $this->migration = new GFMigration20260909001AdvisorsPartners();
    }

    #[Test]
    public function it_sorts_after_the_booking_inquiry_migration(): void
    {
        $bookingInquiry = new GFMigration20260907001BookingInquiry();

        $this->assertGreaterThan(
            $bookingInquiry->getVersion(),
            $this->migration->getVersion()
        );
    }

    /** @return string[] */
    private function allTables()
    {
        return [
            'gf_advisor',
            'gf_advisor_lang',
            'gf_advisor_source',
            'gf_partner',
            'gf_partner_lang',
            'gf_partner_source',
        ];
    }

    #[Test]
    public function it_creates_all_six_tables(): void
    {
        $schema = new GFRecordingSchemaHelper();

        $this->assertTrue($this->migration->up($schema));

        $this->assertSame(
            array_map(function ($table) {
                return 'createTable:' . $table;
            }, $this->allTables()),
            $schema->getOperationKeys()
        );
    }

    #[Test]
    public function running_it_twice_changes_nothing_the_second_time(): void
    {
        $schema = new GFRecordingSchemaHelper($this->allTables());

        $this->assertTrue($this->migration->up($schema));
        $this->assertSame([], $schema->getOperationKeys());
    }

    #[Test]
    public function a_partially_migrated_schema_only_creates_the_missing_tables(): void
    {
        $schema = new GFRecordingSchemaHelper(['gf_advisor', 'gf_advisor_lang', 'gf_advisor_source']);

        $this->assertTrue($this->migration->up($schema));

        $this->assertSame(
            ['createTable:gf_partner', 'createTable:gf_partner_lang', 'createTable:gf_partner_source'],
            $schema->getOperationKeys()
        );
    }

    #[Test]
    public function down_drops_all_six_tables_lang_and_source_first(): void
    {
        $schema = new GFRecordingSchemaHelper($this->allTables());

        $this->assertTrue($this->migration->down($schema));

        $this->assertSame(
            array_map(function ($table) {
                return 'dropTable:' . $table;
            }, array_reverse($this->allTables())),
            $schema->getOperationKeys()
        );
    }

    #[Test]
    public function down_tolerates_tables_that_were_never_created(): void
    {
        $schema = new GFRecordingSchemaHelper();

        $this->assertTrue($this->migration->down($schema));
        $this->assertSame([], $schema->getOperationKeys());
    }
}
