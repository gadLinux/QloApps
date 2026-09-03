<?php
/**
 * 2026 GF Experiences
 *
 * Tally of what an import did.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFImportResult
{
    /** @var int */
    private $created = 0;

    /** @var int */
    private $updated = 0;

    /** @var int */
    private $failed = 0;

    /** @var int Rows removed by a reload before importing. */
    private $deleted = 0;

    /** @var string[] */
    private $errors = [];

    public function recordCreated()
    {
        $this->created++;
    }

    public function recordUpdated()
    {
        $this->updated++;
    }

    public function recordFailure($message)
    {
        $this->failed++;
        $this->errors[] = $message;
    }

    public function recordDeleted($count)
    {
        $this->deleted += (int) $count;
    }

    public function addErrors(array $messages)
    {
        $this->errors = array_merge($this->errors, $messages);
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function getUpdated()
    {
        return $this->updated;
    }

    public function getFailed()
    {
        return $this->failed;
    }

    public function getDeleted()
    {
        return $this->deleted;
    }

    /** @return string[] */
    public function getErrors()
    {
        return $this->errors;
    }

    public function isSuccessful()
    {
        return $this->failed === 0 && empty($this->errors);
    }

    public function getTotalProcessed()
    {
        return $this->created + $this->updated;
    }
}
