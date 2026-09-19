<?php
/**
 * 2026 GF Experiences
 *
 * Turns the establishments CSV into GFEstablishment objects.
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

class GFEstablishmentCsvReader
{
    /** Standard CSV field separator. */
    const DELIMITER = ',';

    /** Standard CSV quote character. */
    const ENCLOSURE = '"';

    /**
     * RFC 4180 has no escape character — a quote inside a quoted field is
     * written twice. Passing an empty string selects that behaviour and is
     * required explicitly from PHP 8.4, where the old default is deprecated.
     */
    const ESCAPE = '';

    /** @var string[] Problems found while reading; rows are skipped, not fatal. */
    private $errors = [];

    /**
     * @param  string $path Absolute path to the CSV.
     * @return GFEstablishment[]
     * @throws GFImportException When the file cannot be read at all.
     */
    public function read($path)
    {
        $this->errors = [];

        $handle = $this->open($path);
        $columns = $this->readHeader($handle, $path);

        $establishments = [];
        $lineNumber = 1;

        while (($row = $this->readRow($handle)) !== false) {
            $lineNumber++;

            if ($this->isBlank($row)) {
                continue;
            }

            $establishment = $this->toEstablishment($row, $columns, $lineNumber);

            if ($establishment !== null) {
                $establishments[] = $establishment;
            }
        }

        fclose($handle);

        return $establishments;
    }

    /**
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return resource
     * @throws GFImportException
     */
    private function open($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new GFImportException('CSV not found or unreadable: ' . $path);
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new GFImportException('Could not open CSV: ' . $path);
        }

        return $handle;
    }

    /**
     * @return array<string, int> Header name => column offset.
     * @throws GFImportException
     */
    private function readHeader($handle, $path)
    {
        $header = $this->readRow($handle);

        if ($header === false) {
            fclose($handle);

            throw new GFImportException('CSV is empty: ' . $path);
        }

        return array_flip(array_map('trim', $header));
    }

    /**
     * One row, with the CSV dialect stated explicitly.
     *
     * @return array|false
     */
    private function readRow($handle)
    {
        return fgetcsv($handle, 0, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);
    }

    private function isBlank(array $row)
    {
        return count($row) === 1 && trim((string) $row[0]) === '';
    }

    /**
     * @return GFEstablishment|null Null when the row is unusable.
     */
    private function toEstablishment(array $row, array $columns, $lineNumber)
    {
        $name = $this->value($row, $columns, 'Name');

        if ($name === '') {
            $this->errors[] = 'Line ' . $lineNumber . ': missing Name, row skipped';

            return null;
        }

        $establishment = new GFEstablishment();
        $establishment->name = $name;
        $establishment->sourceId = $this->resolveSourceId($row, $columns, $name);
        $establishment->description = $this->value($row, $columns, 'Description');
        $establishment->type = $this->parseType($this->value($row, $columns, 'Type'));
        $establishment->certification = $this->parseCertification(
            $this->value($row, $columns, 'Certification_Type')
        );
        $establishment->country = $this->value($row, $columns, 'Country');
        $establishment->city = $this->resolveCity($row, $columns);
        $establishment->destinationUrl = $this->value($row, $columns, 'Destination_URL');

        // Only the file name is dependable: the path in the source file is
        // relative to wherever that file was authored.
        $establishment->imageFile = basename($this->value($row, $columns, 'Image_Path'));

        return $establishment;
    }

    /**
     * Prefer the source file's own id; fall back to a hash of the name so a
     * file without an ID column is still reloadable.
     */
    private function resolveSourceId(array $row, array $columns, $name)
    {
        $id = $this->value($row, $columns, 'ID');

        return $id !== '' ? $id : md5($name);
    }

    private function resolveCity(array $row, array $columns)
    {
        $city = $this->value($row, $columns, 'City/Region');

        return $city !== '' ? $city : $this->value($row, $columns, 'Location');
    }

    /**
     * Unrecognised types become EXPERIENCE (decision D10) rather than being
     * dropped, so nothing silently disappears from the catalogue.
     */
    private function parseType($raw)
    {
        $type = Tools::strtoupper(trim($raw));

        return in_array($type, GFEstablishment::getValidTypes(), true)
            ? $type
            : GFEstablishment::TYPE_EXPERIENCE;
    }

    /**
     * Unknown certification stays null. Decision D11 requires absence to be
     * represented honestly rather than guessed in either direction.
     */
    private function parseCertification($raw)
    {
        $value = Tools::strtolower(trim($raw));

        if (strpos($value, 'dedicated') !== false) {
            return GFEstablishment::CERTIFICATION_DEDICATED;
        }

        if (strpos($value, 'option') !== false) {
            return GFEstablishment::CERTIFICATION_OPTIONS;
        }

        return null;
    }

    private function value(array $row, array $columns, $columnName)
    {
        if (!isset($columns[$columnName], $row[$columns[$columnName]])) {
            return '';
        }

        return trim($row[$columns[$columnName]]);
    }
}
