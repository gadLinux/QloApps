<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the establishments CSV reader — Story 1.8.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFEstablishmentCsvReader::class)]
class GFEstablishmentCsvReaderTest extends TestCase
{
    /** @var GFEstablishmentCsvReader */
    private $reader;

    /** @var string */
    private $fixture;

    protected function setUp(): void
    {
        $this->reader = new GFEstablishmentCsvReader();
        $this->fixture = dirname(__DIR__) . '/fixtures/establishments-sample.csv';
    }

    #[Test]
    public function it_reads_every_usable_row(): void
    {
        $establishments = $this->reader->read($this->fixture);

        // Five data rows, one of which has no name and is skipped.
        $this->assertCount(4, $establishments);
        $this->assertContainsOnlyInstancesOf(GFEstablishment::class, $establishments);
    }

    #[Test]
    public function it_maps_every_column_onto_the_establishment(): void
    {
        $panella = $this->readByName('Cantine Panella');

        $this->assertSame('1', $panella->sourceId);
        $this->assertSame('Cantine Panella', $panella->name);
        $this->assertSame(GFEstablishment::TYPE_RESTAURANT, $panella->type);
        $this->assertSame('Canada', $panella->country);
        $this->assertSame('Montreal', $panella->city);
        $this->assertSame('https://www.panella.ca/en', $panella->destinationUrl);
        $this->assertSame(GFEstablishment::CERTIFICATION_DEDICATED, $panella->certification);
        $this->assertStringContainsString('flour mixtures', $panella->description);
    }

    #[Test]
    public function it_skips_rows_without_a_name_and_reports_them(): void
    {
        $establishments = $this->reader->read($this->fixture);

        $names = array_map(fn (GFEstablishment $e) => $e->name, $establishments);
        $this->assertNotContains('', $names);

        $errors = $this->reader->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('missing Name', $errors[0]);
    }

    /**
     * Decision D10: an unrecognised type is reclassified rather than dropped,
     * so nothing silently disappears from the catalogue.
     */
    #[Test]
    public function it_reclassifies_an_unknown_type_as_experience(): void
    {
        $tour = $this->readByName('Tour Gastronomicos');

        $this->assertSame(GFEstablishment::TYPE_EXPERIENCE, $tour->type);
    }

    /**
     * Decision D11: absence of certification is represented honestly, never
     * guessed in either direction.
     */
    #[Test]
    public function it_leaves_missing_certification_null(): void
    {
        $tour = $this->readByName('Tour Gastronomicos');

        $this->assertNull($tour->certification);
    }

    #[Test]
    public function it_matches_certification_case_insensitively(): void
    {
        $sicilia = $this->readByName('Caffè Sicília');

        $this->assertSame(GFEstablishment::CERTIFICATION_OPTIONS, $sicilia->certification);
    }

    #[Test]
    public function it_falls_back_to_location_when_city_is_empty(): void
    {
        $tour = $this->readByName('Tour Gastronomicos');

        $this->assertSame('Castilla', $tour->city);
    }

    #[Test]
    public function it_preserves_accents_and_quotes(): void
    {
        $sicilia = $this->readByName('Caffè Sicília');

        $this->assertSame('Caffè Sicília', $sicilia->name);
        $this->assertStringContainsString('"inner quote"', $sicilia->description);
    }

    #[Test]
    public function it_throws_when_the_file_does_not_exist(): void
    {
        $this->expectException(GFImportException::class);
        $this->expectExceptionMessage('CSV not found');

        $this->reader->read('/nonexistent/establishments.csv');
    }

    #[Test]
    public function it_resets_errors_between_reads(): void
    {
        $this->reader->read($this->fixture);
        $this->assertNotEmpty($this->reader->getErrors());

        $this->reader->read(dirname(__DIR__) . '/fixtures/establishments-clean.csv');
        $this->assertSame([], $this->reader->getErrors());
    }

    private function readByName(string $name): GFEstablishment
    {
        foreach ($this->reader->read($this->fixture) as $establishment) {
            if ($establishment->name === $name) {
                return $establishment;
            }
        }

        $this->fail('No establishment named "' . $name . '" in the fixture');
    }
}
