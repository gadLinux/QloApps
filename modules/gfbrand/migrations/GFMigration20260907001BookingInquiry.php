<?php
/**
 * 2026 GF Experiences
 *
 * Creates gf_booking_inquiry — story 1.12.
 *
 * The live WordPress form emails an enquiry and forgets it; nothing stores a
 * submission anywhere. This table is the fix: every enquiry becomes a durable
 * row the admin inbox (AdminGfInquiriesController) can list, filter and work.
 *
 * consent_text/consent_at exist because "consent = 1" proves nothing months
 * later if the wording on the page has since changed. Storing the wording as
 * shown, with a timestamp, lets an older row still evidence what its
 * submitter actually agreed to.
 *
 * ip_address is not in the story's own DDL sample. It was added here because
 * AC-7 requires rate limiting "per IP and per email", and there is nowhere
 * else in this schema to read an IP from — GfInquiry::countRecentByIp() reads
 * this column.
 *
 * dest_country is VARCHAR(100), not VARCHAR(2) as the story's sample DDL has
 * it. home_country holds a real ISO 3166-1 alpha-2 code because AC-8 sources
 * it from PrestaShop's own Country table (which speaks ISO codes). dest_country
 * is a different kind of value: it must match gf_country on ps_product (AC-3
 * queries the establishment table filtered by it), and that column has always
 * held plain names — "Costa Rica", not "CR" — with no ISO mapping layer
 * anywhere in this codebase. It also carries the literal sentinel "OTHER" for
 * a destination not yet in the catalogue. Matching gf_country's own type was
 * judged safer than introducing a lookup table this story does not need.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFMigration20260907001BookingInquiry implements GFMigrationInterface
{
    const TABLE = 'gf_booking_inquiry';

    public function getVersion()
    {
        return '20260907_001';
    }

    public function getDescription()
    {
        return 'Create gf_booking_inquiry';
    }

    public function up(GFSchemaHelper $schema)
    {
        return $schema->createTable(self::TABLE, '
            `id_gf_booking_inquiry` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `first_name` VARCHAR(128) NOT NULL,
            `last_name` VARCHAR(128) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(32) DEFAULT NULL,
            `home_country` VARCHAR(2) DEFAULT NULL,
            `home_city` VARCHAR(128) DEFAULT NULL,
            `id_gf_partner` INT UNSIGNED DEFAULT NULL,
            `promo_code` VARCHAR(16) DEFAULT NULL,
            `dest_country` VARCHAR(100) DEFAULT NULL,
            `id_product` INT UNSIGNED DEFAULT NULL,
            `travel_date` DATE DEFAULT NULL,
            `duration` VARCHAR(64) DEFAULT NULL,
            `adults` TINYINT UNSIGNED DEFAULT NULL,
            `children` TINYINT UNSIGNED DEFAULT NULL,
            `best_time_call` VARCHAR(128) DEFAULT NULL,
            `referral_source` VARCHAR(128) DEFAULT NULL,
            `message` TEXT,
            `consent_text` TEXT NOT NULL,
            `consent_at` DATETIME NOT NULL,
            `status` ENUM(\'new\',\'in_progress\',\'quoted\',\'won\',\'lost\') NOT NULL DEFAULT \'new\',
            `id_employee` INT UNSIGNED DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_gf_booking_inquiry`),
            KEY `idx_status_date` (`status`, `date_add`),
            KEY `idx_email` (`email`),
            KEY `idx_ip_date` (`ip_address`, `date_add`)
        ');
    }

    public function down(GFSchemaHelper $schema)
    {
        return $schema->dropTable(self::TABLE);
    }
}
