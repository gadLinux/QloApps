<?php
/**
 * 2026 GF Experiences
 *
 * Reads the room-type CSV and groups it by hotel.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFRoomTypeCsvReader
{
    const DELIMITER = ',';
    const ENCLOSURE = '"';
    const ESCAPE = '';

    /** @var string[] */
    private $errors = [];

    /**
     * @param  string $path
     * @return array<string, GFRoomType[]> Hotel source id => its room types.
     * @throws GFImportException When the file cannot be read.
     */
    public function readGroupedByHotel($path)
    {
        $this->errors = [];

        if (!is_file($path) || !is_readable($path)) {
            throw new GFImportException('Room type CSV not found or unreadable: ' . $path);
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new GFImportException('Could not open room type CSV: ' . $path);
        }

        $header = fgetcsv($handle, 0, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);

        if ($header === false) {
            fclose($handle);

            throw new GFImportException('Room type CSV is empty: ' . $path);
        }

        $columns = array_flip(array_map('trim', $header));
        $grouped = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, self::DELIMITER, self::ENCLOSURE, self::ESCAPE)) !== false) {
            $lineNumber++;

            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $roomType = $this->toRoomType($row, $columns, $lineNumber);

            if ($roomType !== null) {
                $grouped[$roomType->hotelSourceId][] = $roomType;
            }
        }

        fclose($handle);

        return $grouped;
    }

    /**
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return GFRoomType|null
     */
    private function toRoomType(array $row, array $columns, $lineNumber)
    {
        $hotelId = $this->value($row, $columns, 'Hotel_ID');
        $code = $this->value($row, $columns, 'Code');
        $name = $this->value($row, $columns, 'Name');

        if ($hotelId === '' || $code === '' || $name === '') {
            $this->errors[] = 'Line ' . $lineNumber . ': needs Hotel_ID, Code and Name, row skipped';

            return null;
        }

        $roomType = new GFRoomType();
        $roomType->hotelSourceId = $hotelId;
        $roomType->code = $code;
        $roomType->name = $name;
        $roomType->description = $this->value($row, $columns, 'Description');
        $roomType->price = (float) $this->value($row, $columns, 'Price');
        $roomType->adults = $this->intOrDefault($row, $columns, 'Adults', 2);
        $roomType->children = $this->intOrDefault($row, $columns, 'Children', 0);
        $roomType->roomCount = max(1, $this->intOrDefault($row, $columns, 'Rooms', 1));

        return $roomType;
    }

    private function intOrDefault(array $row, array $columns, $columnName, $default)
    {
        $value = $this->value($row, $columns, $columnName);

        return $value === '' ? $default : (int) $value;
    }

    private function value(array $row, array $columns, $columnName)
    {
        if (!isset($columns[$columnName], $row[$columns[$columnName]])) {
            return '';
        }

        return trim($row[$columns[$columnName]]);
    }
}
