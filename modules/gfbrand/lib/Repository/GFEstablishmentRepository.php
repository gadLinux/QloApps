<?php
/**
 * 2026 GF Experiences
 *
 * All SQL touching the establishment fields on ps_product.
 *
 * Nothing above this class writes SQL against those columns; nothing in it
 * renders output or decides policy.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFEstablishmentRepository
{
    const TABLE = 'product';

    /** @var Db */
    private $db;

    public function __construct(Db $db = null)
    {
        $this->db = $db ?: Db::getInstance();
    }

    /**
     * Product id previously imported under this source id.
     *
     * @return int|null
     */
    public function findIdBySourceId($sourceId)
    {
        $id = $this->readDb()->getValue(
            'SELECT `id_product` FROM `' . $this->table() . '`
             WHERE `gf_source_id` = \'' . pSQL($sourceId) . '\''
        );

        return $id ? (int) $id : null;
    }

    /**
     * Every product id the importer created.
     *
     * @return int[]
     */
    public function findImportedIds()
    {
        $rows = $this->readDb()->executeS(
            'SELECT `id_product` FROM `' . $this->table() . '` WHERE ' . $this->importedCondition()
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map('intval', array_column($rows, 'id_product'));
    }

    /**
     * How many establishments exist, broken down by type.
     *
     * @return array<string, int> Type => count.
     */
    public function countByType()
    {
        $rows = $this->readDb()->executeS(
            'SELECT `gf_type`, COUNT(*) AS total FROM `' . $this->table() . '`
             WHERE ' . $this->importedCondition() . '
             GROUP BY `gf_type`'
        );

        if (!is_array($rows)) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['gf_type']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countAll()
    {
        return (int) $this->readDb()->getValue(
            'SELECT COUNT(*) FROM `' . $this->table() . '` WHERE ' . $this->importedCondition()
        );
    }

    /**
     * Write the GF fields onto an existing product row.
     *
     * @param  int $idProduct
     * @return bool
     */
    public function saveFields($idProduct, GFEstablishment $establishment)
    {
        $assignments = [
            '`gf_source_id` = ' . $this->quote($establishment->sourceId),
            '`gf_type` = ' . $this->quote($establishment->type),
            '`gf_destination_url` = ' . $this->quote($establishment->destinationUrl),
            '`gf_certification` = ' . $this->quote($establishment->certification),
            '`gf_country` = ' . $this->quote($establishment->country),
            '`gf_city` = ' . $this->quote($establishment->city),
            '`gf_has_channel_manager` = ' . (int) $establishment->hasChannelManager,
            '`gf_channel_manager_status` = ' . $this->quote($establishment->channelManagerStatus),
            '`gf_featured_home` = ' . (int) $establishment->featuredHome,
        ];

        return (bool) $this->db->execute(
            'UPDATE `' . $this->table() . '`
             SET ' . implode(', ', $assignments) . '
             WHERE `id_product` = ' . (int) $idProduct
        );
    }

    /**
     * True once the migration has run. Guards features that read the columns
     * before the schema catches up.
     */
    public function isSchemaReady()
    {
        $count = $this->readDb()->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
               AND TABLE_NAME = \'' . pSQL($this->table()) . '\'
               AND COLUMN_NAME = \'gf_source_id\''
        );

        return (int) $count > 0;
    }

    /**
     * Rows the importer owns. Products created by hand have no source id and
     * are therefore never matched by a reload.
     */
    private function importedCondition()
    {
        return '`gf_source_id` IS NOT NULL AND `gf_source_id` != \'\'';
    }

    /**
     * NULL for null, a quoted escaped string otherwise. Keeps "no
     * certification" distinct from "certification recorded as empty".
     */
    private function quote($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        return '\'' . pSQL($value) . '\'';
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
