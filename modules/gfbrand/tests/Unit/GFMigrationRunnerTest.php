<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the migration runner — Story 1.8.
 *
 * The runner executes on every module load, so "already up to date" must be a
 * cheap no-op and a failure must not record a version it did not apply.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFMigrationRunner::class)]
class GFMigrationRunnerTest extends TestCase
{
    #[Test]
    public function it_applies_a_pending_migration_and_records_its_version(): void
    {
        $migration = new GFFakeMigration('20260101_001');
        $repository = new GFInMemoryMigrationRepository();
        $runner = $this->makeRunner([$migration], $repository);

        $result = $runner->migrate();

        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($result->hasChanges());
        $this->assertSame(['20260101_001'], $result->getApplied());
        $this->assertSame(['20260101_001'], $repository->getApplied());
        $this->assertSame(1, $migration->upCalls);
    }

    /**
     * The common case: the module loads, everything has already run.
     */
    #[Test]
    public function it_skips_a_migration_that_already_ran(): void
    {
        $migration = new GFFakeMigration('20260101_001');
        $repository = new GFInMemoryMigrationRepository(['20260101_001']);
        $runner = $this->makeRunner([$migration], $repository);

        $result = $runner->migrate();

        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->hasChanges());
        $this->assertSame(0, $migration->upCalls);
        $this->assertStringContainsString('already up to date', $result->getSummary());
    }

    #[Test]
    public function it_runs_migrations_in_ascending_version_order(): void
    {
        $order = [];
        $third = new GFFakeMigration('20260103_001', $order);
        $first = new GFFakeMigration('20260101_001', $order);
        $second = new GFFakeMigration('20260102_001', $order);

        // Deliberately constructed out of order.
        $runner = $this->makeRunner([$third, $first, $second], new GFInMemoryMigrationRepository());

        $result = $runner->migrate();

        $this->assertSame(
            ['20260101_001', '20260102_001', '20260103_001'],
            $result->getApplied()
        );
    }

    #[Test]
    public function it_only_applies_versions_that_are_still_pending(): void
    {
        $done = new GFFakeMigration('20260101_001');
        $todo = new GFFakeMigration('20260102_001');
        $repository = new GFInMemoryMigrationRepository(['20260101_001']);
        $runner = $this->makeRunner([$done, $todo], $repository);

        $result = $runner->migrate();

        $this->assertSame(['20260102_001'], $result->getApplied());
        $this->assertSame(0, $done->upCalls);
        $this->assertSame(1, $todo->upCalls);
    }

    /**
     * Stopping at the first failure leaves later versions unrecorded, so the
     * next module load retries from the point that broke.
     */
    #[Test]
    public function it_stops_at_the_first_failure_and_leaves_later_versions_pending(): void
    {
        $failing = new GFFakeMigration('20260101_001');
        $failing->succeeds = false;
        $later = new GFFakeMigration('20260102_001');
        $repository = new GFInMemoryMigrationRepository();
        $runner = $this->makeRunner([$failing, $later], $repository);

        $result = $runner->migrate();

        $this->assertFalse($result->isSuccessful());
        $this->assertSame([], $repository->getApplied());
        $this->assertSame(0, $later->upCalls);
        $this->assertStringContainsString('20260101_001', $result->getErrors()[0]);
    }

    #[Test]
    public function an_exception_in_a_migration_is_reported_not_thrown(): void
    {
        $exploding = new GFFakeMigration('20260101_001');
        $exploding->throwOnUp = 'table is locked';
        $repository = new GFInMemoryMigrationRepository();
        $runner = $this->makeRunner([$exploding], $repository);

        $result = $runner->migrate();

        $this->assertFalse($result->isSuccessful());
        $this->assertSame([], $repository->getApplied());
        $this->assertStringContainsString('table is locked', $result->getErrors()[0]);
    }

    #[Test]
    public function rollback_reverses_applied_migrations_newest_first(): void
    {
        $order = [];
        $first = new GFFakeMigration('20260101_001', $order);
        $second = new GFFakeMigration('20260102_001', $order);
        $repository = new GFInMemoryMigrationRepository(['20260101_001', '20260102_001']);
        $runner = $this->makeRunner([$first, $second], $repository);

        $result = $runner->rollback();

        $this->assertSame(['20260102_001', '20260101_001'], $result->getApplied());
        $this->assertSame([], $repository->getApplied());
        $this->assertSame(1, $first->downCalls);
        $this->assertSame(1, $second->downCalls);
    }

    #[Test]
    public function rollback_ignores_migrations_that_never_ran(): void
    {
        $neverRan = new GFFakeMigration('20260101_001');
        $runner = $this->makeRunner([$neverRan], new GFInMemoryMigrationRepository());

        $runner->rollback();

        $this->assertSame(0, $neverRan->downCalls);
    }

    #[Test]
    public function it_reports_whether_work_is_outstanding(): void
    {
        $migration = new GFFakeMigration('20260101_001');

        $pending = $this->makeRunner([$migration], new GFInMemoryMigrationRepository());
        $upToDate = $this->makeRunner([$migration], new GFInMemoryMigrationRepository(['20260101_001']));

        $this->assertTrue($pending->hasPending());
        $this->assertFalse($upToDate->hasPending());
        $this->assertCount(1, $upToDate->getApplied());
    }

    #[Test]
    public function the_summary_names_what_was_applied(): void
    {
        $runner = $this->makeRunner(
            [new GFFakeMigration('20260101_001')],
            new GFInMemoryMigrationRepository()
        );

        $summary = $runner->migrate()->getSummary();

        $this->assertStringContainsString('Applied 1 migration', $summary);
        $this->assertStringContainsString('20260101_001', $summary);
    }

    private function makeRunner(array $migrations, GFMigrationRepository $repository): GFMigrationRunner
    {
        return new GFMigrationRunner($migrations, $repository, new GFRecordingSchemaHelper());
    }
}

/**
 * Migration that records how it was called instead of touching a schema.
 */
class GFFakeMigration implements GFMigrationInterface
{
    public $upCalls = 0;
    public $downCalls = 0;
    public $succeeds = true;
    public $throwOnUp = null;

    private $version;
    private $order;

    public function __construct($version, array &$order = null)
    {
        $this->version = $version;
        $this->order = &$order;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getDescription()
    {
        return 'Fake migration ' . $this->version;
    }

    public function up(GFSchemaHelper $schema)
    {
        $this->upCalls++;

        if ($this->order !== null) {
            $this->order[] = $this->version;
        }

        if ($this->throwOnUp !== null) {
            throw new RuntimeException($this->throwOnUp);
        }

        return $this->succeeds;
    }

    public function down(GFSchemaHelper $schema)
    {
        $this->downCalls++;

        return true;
    }
}
