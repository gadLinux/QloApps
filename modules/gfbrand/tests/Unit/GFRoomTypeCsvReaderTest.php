<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the room-type reader.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFRoomTypeCsvReader::class)]
class GFRoomTypeCsvReaderTest extends TestCase
{
    /** @var GFRoomTypeCsvReader */
    private $reader;

    /** @var string */
    private $fixture;

    protected function setUp(): void
    {
        $this->reader = new GFRoomTypeCsvReader();
        $this->fixture = dirname(__DIR__) . '/fixtures/room-types-sample.csv';
    }

    #[Test]
    public function it_groups_room_types_under_their_hotel(): void
    {
        $grouped = $this->reader->readGroupedByHotel($this->fixture);

        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped['3']);
        $this->assertCount(1, $grouped['4']);
    }

    /**
     * PHP coerces numeric string array keys to integers, so the group keys
     * come back as ints. Lookup by the string source id still resolves, and
     * that is what GFHotelProvisioner does — pin it so the coercion cannot
     * quietly stop working.
     */
    #[Test]
    public function groups_are_reachable_by_the_string_source_id(): void
    {
        $grouped = $this->reader->readGroupedByHotel($this->fixture);

        $sourceId = '3';

        $this->assertArrayHasKey($sourceId, $grouped);
        $this->assertTrue(isset($grouped[$sourceId]));
        $this->assertSame('Standard Room', $grouped[$sourceId][0]->name);
    }

    #[Test]
    public function it_maps_every_column(): void
    {
        $grouped = $this->reader->readGroupedByHotel($this->fixture);
        $standard = $grouped['3'][0];

        $this->assertSame('3', $standard->hotelSourceId);
        $this->assertSame('STD', $standard->code);
        $this->assertSame('Standard Room', $standard->name);
        $this->assertSame(120.0, $standard->price);
        $this->assertSame(2, $standard->adults);
        $this->assertSame(1, $standard->children);
        $this->assertSame(6, $standard->roomCount);
        $this->assertStringContainsString('gluten-free', $standard->description);
    }

    /**
     * The source id is what makes a room type reloadable, and it has to stay
     * distinct per hotel — two hotels both have a room coded STD.
     */
    #[Test]
    public function source_ids_are_unique_per_hotel(): void
    {
        $grouped = $this->reader->readGroupedByHotel($this->fixture);

        $this->assertSame('3-room-STD', $grouped['3'][0]->getSourceId());
        $this->assertSame('4-room-STD', $grouped['4'][0]->getSourceId());
    }

    #[Test]
    public function it_skips_rows_missing_a_key_field_and_reports_them(): void
    {
        $grouped = $this->reader->readGroupedByHotel($this->fixture);

        $this->assertArrayNotHasKey('', $grouped);
        $this->assertCount(1, $this->reader->getErrors());
        $this->assertStringContainsString('Hotel_ID, Code and Name', $this->reader->getErrors()[0]);
    }

    #[Test]
    public function missing_numbers_fall_back_to_sensible_defaults(): void
    {
        $grouped = $this->reader->readGroupedByHotel($this->fixture);
        $sparse = $grouped['4'][0];

        $this->assertSame(2, $sparse->adults);
        $this->assertSame(0, $sparse->children);
        // Rooms is blank in the fixture; a room type must have at least one.
        $this->assertSame(1, $sparse->roomCount);
    }

    #[Test]
    public function it_reads_a_room_types_own_photograph(): void
    {
        $grouped = $this->reader->readGroupedByHotel($this->fixture);

        $this->assertSame('deluxe-room.jpg', $grouped['3'][1]->imageFile);
    }

    /**
     * No room photography exists for most of the catalogue, so such a row
     * reads as empty and GFRoomTypeFactory falls back to the hotel's picture.
     */
    #[Test]
    public function a_room_without_its_own_photograph_reads_as_empty(): void
    {
        $grouped = $this->reader->readGroupedByHotel($this->fixture);

        $this->assertSame('', $grouped['3'][0]->imageFile);
        $this->assertSame('', (new GFRoomType())->imageFile);
    }

    #[Test]
    public function max_guests_is_adults_plus_children(): void
    {
        $roomType = new GFRoomType();
        $roomType->adults = 2;
        $roomType->children = 3;

        $this->assertSame(5, $roomType->getMaxGuests());
    }

    #[Test]
    public function it_throws_when_the_file_is_missing(): void
    {
        $this->expectException(GFImportException::class);
        $this->expectExceptionMessage('Room type CSV not found');

        $this->reader->readGroupedByHotel('/nonexistent/room-types.csv');
    }
}
