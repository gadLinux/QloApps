<?php
/**
 * 2026 GF Experiences
 *
 * Adds gf_booking_inquiry.is_suspected_spam and .dest_country_other — story
 * 1.12 review fixes.
 *
 * is_suspected_spam: a tripped honeypot used to discard the submission with
 * no trace at all: no log line, no row, indistinguishable from a real guest
 * whose browser autofilled the off-screen trap. That is the same
 * silent-loss failure this whole table exists to fix, just moved one step
 * earlier. The flag lets a suspected-spam row be stored (for the abuse
 * pattern it shows — the admin controller's own docblock already argues
 * that case for a row that slips past the honeypot) without it polluting
 * `status`, which describes the staff workflow on a real enquiry, not
 * whether one is trusted. A plain flag column rather than a new `status`
 * value: `status` is a fixed ENUM staff pick from in the edit screen, and
 * "spam" is not a workflow state anyone would deliberately set an enquiry
 * to — it is metadata about how the row was created.
 *
 * dest_country_other: choosing "Other" for destination stored only the
 * literal sentinel "OTHER" — the guest's actual typed destination was
 * discarded, unlike referral_source's own "Other" escape hatch. The
 * sentinel in dest_country is kept (so reporting can still group on it);
 * this column holds what the guest actually typed.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFMigration20260917001BookingInquirySpamFlag implements GFMigrationInterface
{
    const TABLE = 'gf_booking_inquiry';
    const COLUMN = 'is_suspected_spam';
    const DEST_OTHER_COLUMN = 'dest_country_other';

    public function getVersion()
    {
        return '20260917_001';
    }

    public function getDescription()
    {
        return 'Add gf_booking_inquiry.is_suspected_spam and .dest_country_other';
    }

    public function up(GFSchemaHelper $schema)
    {
        return $schema->addColumn(
            self::TABLE,
            self::COLUMN,
            'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0'
        ) && $schema->addColumn(
            self::TABLE,
            self::DEST_OTHER_COLUMN,
            'VARCHAR(100) DEFAULT NULL'
        );
    }

    public function down(GFSchemaHelper $schema)
    {
        $schema->dropColumn(self::TABLE, self::DEST_OTHER_COLUMN);

        return $schema->dropColumn(self::TABLE, self::COLUMN);
    }
}
