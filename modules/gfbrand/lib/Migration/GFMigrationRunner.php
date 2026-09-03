<?php
/**
 * 2026 GF Experiences
 *
 * Runs pending migrations and records what it applied.
 *
 * Called on module load, so deploying code is enough to bring the schema up to
 * date. Migrations run in ascending version order and the run stops at the
 * first failure, leaving later versions unrecorded so the next load retries.
 *
 * INFRASTRUCTURE LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFMigrationRunner
{
    /** @var GFMigrationInterface[] Ordered by version. */
    private $migrations;

    /** @var GFMigrationRepository */
    private $repository;

    /** @var GFSchemaHelper */
    private $schema;

    /**
     * @param GFMigrationInterface[] $migrations
     */
    public function __construct(
        array $migrations,
        GFMigrationRepository $repository,
        GFSchemaHelper $schema
    ) {
        $this->migrations = $this->sortByVersion($migrations);
        $this->repository = $repository;
        $this->schema = $schema;
    }

    /**
     * Apply every migration that has not run yet.
     */
    public function migrate()
    {
        $result = new GFMigrationResult();

        foreach ($this->getPending() as $migration) {
            if (!$this->apply($migration, $result)) {
                break;
            }
        }

        return $result;
    }

    /**
     * Reverse every applied migration, newest first.
     */
    public function rollback()
    {
        $result = new GFMigrationResult();

        foreach (array_reverse($this->getApplied()) as $migration) {
            $this->revert($migration, $result);
        }

        $this->repository->clear();

        return $result;
    }

    /**
     * @return GFMigrationInterface[]
     */
    public function getPending()
    {
        return array_values(array_filter($this->migrations, function (GFMigrationInterface $migration) {
            return !$this->repository->isApplied($migration->getVersion());
        }));
    }

    /**
     * @return GFMigrationInterface[]
     */
    public function getApplied()
    {
        return array_values(array_filter($this->migrations, function (GFMigrationInterface $migration) {
            return $this->repository->isApplied($migration->getVersion());
        }));
    }

    public function hasPending()
    {
        return !empty($this->getPending());
    }

    private function apply(GFMigrationInterface $migration, GFMigrationResult $result)
    {
        $this->schema->clearLog();

        try {
            $succeeded = $migration->up($this->schema);
        } catch (Exception $exception) {
            $result->addError($migration->getVersion() . ': ' . $exception->getMessage());

            return false;
        }

        if (!$succeeded) {
            $result->addError($migration->getVersion() . ': up() reported failure');

            return false;
        }

        $this->repository->markApplied($migration->getVersion());
        $result->addApplied($migration->getVersion(), $this->schema->getLog());

        return true;
    }

    private function revert(GFMigrationInterface $migration, GFMigrationResult $result)
    {
        $this->schema->clearLog();

        try {
            $migration->down($this->schema);
        } catch (Exception $exception) {
            $result->addError($migration->getVersion() . ': ' . $exception->getMessage());

            return;
        }

        $this->repository->markReverted($migration->getVersion());
        $result->addApplied($migration->getVersion(), $this->schema->getLog());
    }

    /**
     * @param  GFMigrationInterface[] $migrations
     * @return GFMigrationInterface[]
     */
    private function sortByVersion(array $migrations)
    {
        usort($migrations, function (GFMigrationInterface $a, GFMigrationInterface $b) {
            return strcmp($a->getVersion(), $b->getVersion());
        });

        return $migrations;
    }
}
