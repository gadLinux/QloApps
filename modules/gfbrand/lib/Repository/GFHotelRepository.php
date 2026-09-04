<?php
/**
 * 2026 GF Experiences
 *
 * SQL for the hotel rows the importer owns.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFHotelRepository
{
    const TABLE = 'htl_branch_info';

    /** @var Db */
    private $db;

    public function __construct(Db $db = null)
    {
        $this->db = $db ?: Db::getInstance();
    }

    /**
     * True when hotelreservationsystem is present and migrated. Everything
     * hotel-related is skipped when it is not, rather than failing the import.
     */
    public function isAvailable()
    {
        $columns = $this->readDb()->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
               AND TABLE_NAME = \'' . pSQL($this->table()) . '\'
               AND COLUMN_NAME = \'gf_source_id\''
        );

        return (int) $columns > 0;
    }

    /**
     * @return int|null
     */
    public function findIdBySourceId($sourceId)
    {
        $id = $this->readDb()->getValue(
            'SELECT `id` FROM `' . $this->table() . '`
             WHERE `gf_source_id` = \'' . pSQL($sourceId) . '\''
        );

        return $id ? (int) $id : null;
    }

    /**
     * The category holding a hotel's bookable room types.
     *
     * This is where "Book Now" goes (story 1.10 AC-3). It deliberately does
     * not go to the establishment product: that row is the informational
     * record, and the thing a guest actually books is a room type underneath
     * this category. Linking to the product would land them on a page with
     * nothing to reserve.
     *
     * @param  string $sourceId The establishment's source id.
     * @return int|null Null when the hotel was never provisioned.
     */
    public function findCategoryIdBySourceId($sourceId)
    {
        $id = $this->readDb()->getValue(
            'SELECT `id_category` FROM `' . $this->table() . '`
             WHERE `gf_source_id` = \'' . pSQL($sourceId) . '\''
        );

        return $id ? (int) $id : null;
    }

    /**
     * @return int[]
     */
    public function findImportedIds()
    {
        $rows = $this->readDb()->executeS(
            'SELECT `id` FROM `' . $this->table() . '`
             WHERE `gf_source_id` IS NOT NULL AND `gf_source_id` != \'\''
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map('intval', array_column($rows, 'id'));
    }

    public function countAll()
    {
        return (int) $this->readDb()->getValue(
            'SELECT COUNT(*) FROM `' . $this->table() . '`
             WHERE `gf_source_id` IS NOT NULL AND `gf_source_id` != \'\''
        );
    }

    /**
     * Stamp the source id onto a hotel row. HotelBranchInformation has no such
     * field in its ObjectModel definition, so it is written directly rather
     * than by overriding the module's class.
     *
     * @return bool
     */
    public function tagWithSourceId($idHotel, $sourceId)
    {
        return (bool) $this->db->execute(
            'UPDATE `' . $this->table() . '`
             SET `gf_source_id` = \'' . pSQL($sourceId) . '\'
             WHERE `id` = ' . (int) $idHotel
        );
    }

    private function table()
    {
        return _DB_PREFIX_ . self::TABLE;
    }

    private function readDb()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_);
    }
}
