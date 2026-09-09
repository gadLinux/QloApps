<?php
/**
 * 2026 GF Experiences
 *
 * Creates gf_advisor, gf_advisor_lang, gf_partner and gf_partner_lang — story 1.11.
 *
 * The _lang split is load-bearing (D3): bilingual ships at launch, and it is
 * the only shape under which PrestaShop's ObjectModel hands a module
 * translated fields, admin language tabs and front-end language resolution
 * for free. Retrofitting a lang table later would mean rewriting every
 * controller, so the schema is born correct.
 *
 * The _lang tables are dropped before their base tables in down(): with no
 * foreign keys to enforce it, the order only matters for a clean teardown.
 *
 * The two _source tables hold the id each record came from in the seed file.
 * ObjectModel refuses to persist a field it does not own, so the source id
 * cannot live as a column on the base table; a side table the importers own
 * outright is what makes "which rows did the import create" a single query —
 * the same idea as ps_product's gf_source_id column, expressed where
 * ObjectModel allows it.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFMigration20260909001AdvisorsPartners implements GFMigrationInterface
{
    const TABLE_ADVISOR = 'gf_advisor';
    const TABLE_ADVISOR_LANG = 'gf_advisor_lang';
    const TABLE_ADVISOR_SOURCE = 'gf_advisor_source';
    const TABLE_PARTNER = 'gf_partner';
    const TABLE_PARTNER_LANG = 'gf_partner_lang';
    const TABLE_PARTNER_SOURCE = 'gf_partner_source';

    public function getVersion()
    {
        return '20260909_001';
    }

    public function getDescription()
    {
        return 'Create advisor and partner tables';
    }

    public function up(GFSchemaHelper $schema)
    {
        foreach ($this->creationOrder() as $step) {
            if (!$schema->createTable($step[0], $step[1])) {
                return false;
            }
        }

        return true;
    }

    public function down(GFSchemaHelper $schema)
    {
        // Lang and source tables first, so a half-finished rollback never
        // leaves a translated row or a dangling source id behind.
        foreach (array_reverse($this->creationOrder()) as $step) {
            $schema->dropTable($step[0]);
        }

        return true;
    }

    /**
     * @return array<int, array{0: string, 1: string}> table name => DDL body.
     */
    private function creationOrder()
    {
        return [
            [self::TABLE_ADVISOR, $this->advisorBody()],
            [self::TABLE_ADVISOR_LANG, $this->advisorLangBody()],
            [self::TABLE_ADVISOR_SOURCE, $this->advisorSourceBody()],
            [self::TABLE_PARTNER, $this->partnerBody()],
            [self::TABLE_PARTNER_LANG, $this->partnerLangBody()],
            [self::TABLE_PARTNER_SOURCE, $this->partnerSourceBody()],
        ];
    }

    /**
     * @return string Column list and keys, without the CREATE TABLE wrapper.
     */
    private function advisorBody()
    {
        return '
            `id_gf_advisor` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `phone` VARCHAR(32) DEFAULT NULL,
            `phone_e164` VARCHAR(20) DEFAULT NULL,
            `website_url` VARCHAR(500) DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_gf_advisor`),
            KEY `idx_active_position` (`active`, `position`)
        ';
    }

    /**
     * @return string
     */
    private function advisorLangBody()
    {
        return '
            `id_gf_advisor` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `regions_served` VARCHAR(255) DEFAULT NULL,
            `bio` TEXT,
            PRIMARY KEY (`id_gf_advisor`, `id_lang`)
        ';
    }

    /**
     * @return string
     */
    private function partnerBody()
    {
        return '
            `id_gf_partner` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `logo` VARCHAR(255) DEFAULT NULL,
            `website_url` VARCHAR(500) DEFAULT NULL,
            `category` VARCHAR(64) DEFAULT NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_gf_partner`),
            KEY `idx_active_position` (`active`, `position`)
        ';
    }

    /**
     * @return string
     */
    private function partnerLangBody()
    {
        return '
            `id_gf_partner` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT,
            PRIMARY KEY (`id_gf_partner`, `id_lang`)
        ';
    }

    /**
     * @return string
     */
    private function advisorSourceBody()
    {
        return '
            `id_gf_advisor` INT UNSIGNED NOT NULL,
            `source_id` VARCHAR(50) NOT NULL,
            PRIMARY KEY (`id_gf_advisor`),
            KEY `idx_source` (`source_id`)
        ';
    }

    /**
     * @return string
     */
    private function partnerSourceBody()
    {
        return '
            `id_gf_partner` INT UNSIGNED NOT NULL,
            `source_id` VARCHAR(50) NOT NULL,
            PRIMARY KEY (`id_gf_partner`),
            KEY `idx_source` (`source_id`)
        ';
    }
}
