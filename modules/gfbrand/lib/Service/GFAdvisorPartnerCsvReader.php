<?php
/**
 * 2026 GF Experiences
 *
 * Turns the advisor and partner CSVs into seed objects — story 1.11, AC-2.
 *
 * Two files, two readers, one class: advisors and partners have no shared
 * columns beyond the id, and forcing them through one shape would only add
 * noise.
 *
 * Columns are located by header name, so the source file's column order can
 * change without breaking the import.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFAdvisorPartnerCsvReader
{
    const DELIMITER = ',';
    const ENCLOSURE = '"';
    const ESCAPE = '';

    /** @var string[] Problems found while reading; rows are skipped, not fatal. */
    private $errors = [];

    /**
     * @param  string $path Absolute path to the advisors CSV.
     * @return GFAdvisorSeed[]
     */
    public function readAdvisors($path)
    {
        $rows = $this->readRows($path);

        if ($rows === false) {
            return [];
        }

        list($columns, $rows) = $rows;
        $advisors = [];

        foreach ($rows as $lineNumber => $row) {
            $name = $this->value($row, $columns, 'name');

            if ($name === '') {
                $this->errors[] = 'Line ' . $lineNumber . ': missing name, row skipped';
                continue;
            }

            $advisor = new GFAdvisorSeed();
            $advisor->sourceId = $this->sourceId($row, $columns, $name);
            $advisor->name = $name;
            $advisor->regionsServed = $this->value($row, $columns, 'regions_served');
            $advisor->phone = $this->value($row, $columns, 'phone');
            $advisor->phoneE164 = $this->value($row, $columns, 'phone_e164');
            $advisor->websiteUrl = $this->value($row, $columns, 'website_url');
            $advisor->email = $this->value($row, $columns, 'email');
            $advisor->imageUrl = basename($this->value($row, $columns, 'image_url'));
            $advisor->bio = $this->value($row, $columns, 'bio');
            $advisor->position = (int) $this->value($row, $columns, 'display_order');
            $advisor->active = (bool) (int) $this->value($row, $columns, 'active');

            $advisors[] = $advisor;
        }

        return $advisors;
    }

    /**
     * @param  string $path Absolute path to the partners CSV.
     * @return GFPartnerSeed[]
     */
    public function readPartners($path)
    {
        $rows = $this->readRows($path);

        if ($rows === false) {
            return [];
        }

        list($columns, $rows) = $rows;
        $partners = [];

        foreach ($rows as $lineNumber => $row) {
            $name = $this->value($row, $columns, 'name');

            if ($name === '') {
                $this->errors[] = 'Line ' . $lineNumber . ': missing name, row skipped';
                continue;
            }

            $partner = new GFPartnerSeed();
            $partner->sourceId = $this->sourceId($row, $columns, $name);
            $partner->name = $name;
            $partner->logoFile = basename($this->value($row, $columns, 'logo_url'));
            $partner->websiteUrl = $this->value($row, $columns, 'website_url');
            $partner->description = $this->value($row, $columns, 'description');
            $partner->category = $this->value($row, $columns, 'category');
            $partner->position = (int) $this->value($row, $columns, 'display_order');
            $partner->active = (bool) (int) $this->value($row, $columns, 'active');

            $partners[] = $partner;
        }

        return $partners;
    }

    /**
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return array{0: array<string, int>, 1: array<int, array>}|false
     */
    private function readRows($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            $this->errors[] = 'CSV not found or unreadable: ' . $path;

            return false;
        }

        $handle = @fopen($path, 'r');

        if ($handle === false) {
            $this->errors[] = 'Could not open CSV: ' . $path;

            return false;
        }

        $header = $this->readRow($handle);

        if ($header === false) {
            fclose($handle);
            $this->errors[] = 'CSV is empty: ' . $path;

            return false;
        }

        $columns = array_flip(array_map('trim', $header));
        $rows = [];
        $lineNumber = 2;

        while (($row = $this->readRow($handle)) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                $lineNumber++;
                continue;
            }

            $rows[$lineNumber] = $row;
            $lineNumber++;
        }

        fclose($handle);

        return [$columns, $rows];
    }

    /**
     * @return array|false
     */
    private function readRow($handle)
    {
        $row = @fgetcsv($handle, 0, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);

        return $row === false ? false : $row;
    }

    private function sourceId(array $row, array $columns, $name)
    {
        $id = $this->value($row, $columns, 'id');

        return $id !== '' ? $id : md5($name);
    }

    private function value(array $row, array $columns, $columnName)
    {
        if (!isset($columns[$columnName], $row[$columns[$columnName]])) {
            return '';
        }

        return trim($row[$columns[$columnName]]);
    }
}
