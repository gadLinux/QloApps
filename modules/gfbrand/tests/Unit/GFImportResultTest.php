<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the import tally — Story 1.8.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFImportResult::class)]
class GFImportResultTest extends TestCase
{
    /** @var GFImportResult */
    private $result;

    protected function setUp(): void
    {
        $this->result = new GFImportResult();
    }

    #[Test]
    public function a_fresh_result_is_empty_and_successful(): void
    {
        $this->assertSame(0, $this->result->getCreated());
        $this->assertSame(0, $this->result->getUpdated());
        $this->assertSame(0, $this->result->getFailed());
        $this->assertSame(0, $this->result->getDeleted());
        $this->assertSame([], $this->result->getErrors());
        $this->assertTrue($this->result->isSuccessful());
    }

    #[Test]
    public function it_counts_each_outcome_separately(): void
    {
        $this->result->recordCreated();
        $this->result->recordCreated();
        $this->result->recordUpdated();
        $this->result->recordDeleted(4);

        $this->assertSame(2, $this->result->getCreated());
        $this->assertSame(1, $this->result->getUpdated());
        $this->assertSame(4, $this->result->getDeleted());
    }

    #[Test]
    public function total_processed_counts_writes_but_not_deletions(): void
    {
        $this->result->recordCreated();
        $this->result->recordUpdated();
        $this->result->recordDeleted(10);

        $this->assertSame(2, $this->result->getTotalProcessed());
    }

    #[Test]
    public function a_failure_records_its_message_and_fails_the_result(): void
    {
        $this->result->recordFailure('Hotel Chateau Louis: save failed');

        $this->assertSame(1, $this->result->getFailed());
        $this->assertSame(['Hotel Chateau Louis: save failed'], $this->result->getErrors());
        $this->assertFalse($this->result->isSuccessful());
    }

    /**
     * Rows the reader skipped are reported without counting as write failures:
     * nothing was attempted, so `failed` stays at zero.
     */
    #[Test]
    public function reader_errors_fail_the_result_without_counting_as_failures(): void
    {
        $this->result->addErrors(['Line 4: missing Name, row skipped']);

        $this->assertSame(0, $this->result->getFailed());
        $this->assertFalse($this->result->isSuccessful());
        $this->assertCount(1, $this->result->getErrors());
    }

    #[Test]
    public function deletions_accumulate(): void
    {
        $this->result->recordDeleted(3);
        $this->result->recordDeleted(2);

        $this->assertSame(5, $this->result->getDeleted());
    }
}
