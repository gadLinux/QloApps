<?php
/**
 * 2026 GF Experiences
 *
 * Adds the establishment fields to ps_product — Story 1.8.
 *
 * Establishments are ordinary QloApps products; these columns carry what the
 * stock product has no field for. They live on ps_product rather than in a
 * side table so the catalogue, its filters and the back office keep working
 * without an override of the core Product class.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFMigration20260903001Establishments implements GFMigrationInterface
{
    const TABLE = 'product';
    const INDEX_TYPE_COUNTRY = 'gf_type_country';

    public function getVersion()
    {
        return '20260903_001';
    }

    public function getDescription()
    {
        return 'Add GF establishment fields to product';
    }

    public function up(GFSchemaHelper $schema)
    {
        foreach ($this->getColumns() as $column => $definition) {
            if (!$schema->addColumn(self::TABLE, $column, $definition)) {
                return false;
            }
        }

        // The establishments listing filters on type and country together.
        return $schema->addIndex(self::TABLE, self::INDEX_TYPE_COUNTRY, ['gf_type', 'gf_country']);
    }

    public function down(GFSchemaHelper $schema)
    {
        $schema->dropIndex(self::TABLE, self::INDEX_TYPE_COUNTRY);

        foreach (array_keys($this->getColumns()) as $column) {
            $schema->dropColumn(self::TABLE, $column);
        }

        return true;
    }

    /**
     * Column name => SQL definition.
     *
     * gf_source_id carries the id from the import source. It is what makes the
     * import reloadable: a re-import matches on it instead of creating a second
     * copy, and a reload can delete exactly the rows the importer created.
     *
     * @return array<string, string>
     */
    private function getColumns()
    {
        return [
            'gf_source_id' => 'VARCHAR(50) NULL DEFAULT NULL',
            'gf_type' => "ENUM('HOTEL','RESTAURANT','EXPERIENCE') NULL DEFAULT NULL",
            'gf_destination_url' => 'VARCHAR(500) NULL DEFAULT NULL',
            'gf_certification' => "ENUM('dedicated','options') NULL DEFAULT NULL",
            'gf_country' => 'VARCHAR(100) NULL DEFAULT NULL',
            'gf_city' => 'VARCHAR(200) NULL DEFAULT NULL',
            'gf_has_channel_manager' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'gf_channel_manager_status' => "ENUM('not_connected','active','inactive') NOT NULL DEFAULT 'not_connected'",
            'gf_featured_home' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ];
    }
}
