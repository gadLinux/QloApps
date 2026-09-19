<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the booking-inquiry table migration.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFMigration20260907001BookingInquiry::class)]
class GFMigration20260907001BookingInquiryTest extends TestCase
{
    /** @var GFMigration20260907001BookingInquiry */
    private $migration;

    protected function setUp(): void
    {
        $this->migration = new GFMigration20260907001BookingInquiry();
    }

    #[Test]
    public function it_sorts_after_the_hotel_source_id_migration(): void
    {
        $hotelSourceId = new GFMigration20260903002HotelSourceId();

        $this->assertGreaterThan(
            $hotelSourceId->getVersion(),
            $this->migration->getVersion()
        );
    }

    #[Test]
    public function it_creates_the_table(): void
    {
        $schema = new GFRecordingSchemaHelper();

        $this->assertTrue($this->migration->up($schema));

        $this->assertSame(['createTable:gf_booking_inquiry'], $schema->getOperationKeys());
    }

    #[Test]
    public function running_it_twice_changes_nothing_the_second_time(): void
    {
        $schema = new GFRecordingSchemaHelper(['gf_booking_inquiry']);

        $this->assertTrue($this->migration->up($schema));
        $this->assertSame([], $schema->getOperationKeys());
    }

    #[Test]
    public function down_drops_the_table(): void
    {
        $schema = new GFRecordingSchemaHelper(['gf_booking_inquiry']);

        $this->assertTrue($this->migration->down($schema));
        $this->assertSame(['dropTable:gf_booking_inquiry'], $schema->getOperationKeys());
    }

    #[Test]
    public function down_tolerates_a_table_that_was_never_created(): void
    {
        $schema = new GFRecordingSchemaHelper();

        $this->assertTrue($this->migration->down($schema));
        $this->assertSame([], $schema->getOperationKeys());
    }
}
