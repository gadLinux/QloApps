<?php
/**
 * 2026 GF Experiences
 *
 * Migration repository that remembers versions in memory.
 *
 * Lets the runner be unit tested without Configuration or a database.
 */

class GFInMemoryMigrationRepository extends GFMigrationRepository
{
    /** @var string[] */
    private $applied;

    public function __construct(array $applied = [])
    {
        $this->applied = $applied;
    }

    public function getApplied()
    {
        sort($this->applied);

        return $this->applied;
    }

    public function isApplied($version)
    {
        return in_array($version, $this->applied, true);
    }

    public function markApplied($version)
    {
        if (!in_array($version, $this->applied, true)) {
            $this->applied[] = $version;
        }
    }

    public function markReverted($version)
    {
        $this->applied = array_values(array_diff($this->applied, [$version]));
    }

    public function clear()
    {
        $this->applied = [];
    }
}
