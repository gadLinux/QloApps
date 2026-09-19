<?php
/**
 * 2026 GF Experiences
 *
 * Outcome of a migration run, so callers do not have to interpret booleans.
 *
 * INFRASTRUCTURE LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFMigrationResult
{
    /** @var string[] Versions applied during this run. */
    private $applied = [];

    /** @var string[] Human-readable schema changes. */
    private $changes = [];

    /** @var string[] Failures; a non-empty list means the run aborted. */
    private $errors = [];

    public function addApplied($version, array $changes = [])
    {
        $this->applied[] = $version;
        $this->changes = array_merge($this->changes, $changes);
    }

    public function addError($message)
    {
        $this->errors[] = $message;
    }

    public function isSuccessful()
    {
        return empty($this->errors);
    }

    public function hasChanges()
    {
        return !empty($this->applied);
    }

    /** @return string[] */
    public function getApplied()
    {
        return $this->applied;
    }

    /** @return string[] */
    public function getChanges()
    {
        return $this->changes;
    }

    /** @return string[] */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * One-line summary for logs and the admin panel.
     *
     * @return string
     */
    public function getSummary()
    {
        if (!$this->isSuccessful()) {
            return 'Migration failed: ' . implode('; ', $this->errors);
        }

        if (!$this->hasChanges()) {
            return 'Schema already up to date';
        }

        return sprintf(
            'Applied %d migration(s): %s',
            count($this->applied),
            implode(', ', $this->applied)
        );
    }
}
