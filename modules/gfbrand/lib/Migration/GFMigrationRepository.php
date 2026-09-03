<?php
/**
 * 2026 GF Experiences
 *
 * Remembers which migrations have run.
 *
 * Versions are kept in one configuration row as a JSON list. A dedicated table
 * would be tidier, but it would itself need a migration to exist, and the
 * configuration table is guaranteed present before any of our code runs.
 *
 * INFRASTRUCTURE LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFMigrationRepository
{
    /** Configuration key holding the JSON list of applied versions. */
    const STORAGE_KEY = 'GFBRAND_MIGRATIONS';

    /**
     * @return string[] Applied version identifiers, ascending.
     */
    public function getApplied()
    {
        $raw = Configuration::get(self::STORAGE_KEY);

        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        sort($decoded);

        return $decoded;
    }

    public function isApplied($version)
    {
        return in_array($version, $this->getApplied(), true);
    }

    public function markApplied($version)
    {
        $versions = $this->getApplied();

        if (in_array($version, $versions, true)) {
            return;
        }

        $versions[] = $version;
        $this->store($versions);
    }

    public function markReverted($version)
    {
        $versions = array_values(array_diff($this->getApplied(), [$version]));

        $this->store($versions);
    }

    /**
     * Forget every recorded version. Called on uninstall once the schema
     * itself has been rolled back.
     */
    public function clear()
    {
        Configuration::deleteByName(self::STORAGE_KEY);
    }

    private function store(array $versions)
    {
        sort($versions);

        Configuration::updateValue(self::STORAGE_KEY, json_encode($versions));
    }
}
