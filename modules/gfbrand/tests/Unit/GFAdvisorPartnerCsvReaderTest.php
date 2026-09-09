<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the advisor/partner seed importers — story 1.11, AC-2.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFAdvisorPartnerCsvReader::class)]
class GFAdvisorPartnerCsvReaderTest extends TestCase
{
    private function sample($file)
    {
        return dirname(__DIR__) . '/fixtures/' . $file;
    }

    #[Test]
    public function it_reads_the_three_seed_advisors(): void
    {
        $advisors = (new GFAdvisorPartnerCsvReader())->readAdvisors($this->sample('advisors-sample.csv'));

        $this->assertCount(3, $advisors);

        $first = $advisors[0];
        $this->assertSame('1', $first->sourceId);
        $this->assertSame('Antxon Olmo', $first->name);
        $this->assertSame('Spain', $first->regionsServed);
        $this->assertSame('+34 669 77 14 72', $first->phone);
        $this->assertSame('+34669771472', $first->phoneE164);
        $this->assertSame(1, $first->position);
        $this->assertTrue($first->active);
    }

    /**
     * The bio column carries the literal "PENDING — client to supply bio"
     * until the client answers; that is the file's own marker, and the
     * importer is not the place to invent or drop copy. It is preserved.
     */
    #[Test]
    public function advisor_fields_the_client_has_not_supplied_are_empty_but_the_pending_bio_marker_survives(): void
    {
        $advisors = (new GFAdvisorPartnerCsvReader())->readAdvisors($this->sample('advisors-sample.csv'));

        foreach ($advisors as $advisor) {
            $this->assertSame('', $advisor->websiteUrl);
            $this->assertSame('', $advisor->email);
            $this->assertSame('', $advisor->imageUrl);
            $this->assertNotSame('', $advisor->bio);
        }
    }

    #[Test]
    public function it_reads_the_four_seed_partners(): void
    {
        $partners = (new GFAdvisorPartnerCsvReader())->readPartners($this->sample('partners-sample.csv'));

        $this->assertCount(4, $partners);

        $third = $partners[2];
        $this->assertSame('Coeliaque Quebec', $third->name);
        $this->assertSame('https://www.coeliaque.quebec/', $third->websiteUrl);
        // Only the file name is dependable: the path in the source file is
        // relative to wherever that file was authored.
        $this->assertSame('coeliaque-quebec-logo.png', $third->logoFile);
        $this->assertSame('organization', $third->category);
    }

    #[Test]
    public function a_missing_file_is_a_reported_failure_not_an_exception(): void
    {
        $reader = new GFAdvisorPartnerCsvReader();

        $this->assertSame([], $reader->readAdvisors('/no/such/file.csv'));
        $this->assertNotSame([], $reader->getErrors());
    }

    #[Test]
    public function a_blank_row_is_skipped_silently(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gf-advisors') . '.csv';
        file_put_contents($path, "id,name,regions_served,phone,phone_e164,website_url,email,image_url,bio,display_order,active\n"
            . "1,A Test,Region,+1 111,00111,,,,1,1\n\n"
            . "2,B Test,Region,+1 222,00222,,,,2,1\n");

        $advisors = (new GFAdvisorPartnerCsvReader())->readAdvisors($path);

        unlink($path);

        $this->assertCount(2, $advisors);
    }
}
