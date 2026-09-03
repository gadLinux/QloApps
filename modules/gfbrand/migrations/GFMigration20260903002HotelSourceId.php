<?php
/**
 * 2026 GF Experiences
 *
 * Lets the importer own hotel rows as well as product rows — Story 1.8/1.5.
 *
 * An establishment of type HOTEL becomes a real QloApps hotel, not only an
 * informational product. Carrying the source id on htl_branch_info gives the
 * hotel the same reloadability the products already have: a re-import updates
 * the hotel it created before, and a reload deletes exactly those.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFMigration20260903002HotelSourceId implements GFMigrationInterface
{
    const TABLE = 'htl_branch_info';
    const INDEX_SOURCE = 'gf_source_id';

    public function getVersion()
    {
        return '20260903_002';
    }

    public function getDescription()
    {
        return 'Add GF source id to hotel branch info';
    }

    public function up(GFSchemaHelper $schema)
    {
        // The hotel tables belong to hotelreservationsystem. If that module is
        // absent there is nothing to extend, and that is not a failure.
        if (!$schema->tableExists(self::TABLE)) {
            return true;
        }

        if (!$schema->addColumn(self::TABLE, 'gf_source_id', 'VARCHAR(50) NULL DEFAULT NULL')) {
            return false;
        }

        return $schema->addIndex(self::TABLE, self::INDEX_SOURCE, ['gf_source_id']);
    }

    public function down(GFSchemaHelper $schema)
    {
        if (!$schema->tableExists(self::TABLE)) {
            return true;
        }

        $schema->dropIndex(self::TABLE, self::INDEX_SOURCE);
        $schema->dropColumn(self::TABLE, 'gf_source_id');

        return true;
    }
}
