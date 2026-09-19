<?php
/**
 * 2026 GF Experiences
 *
 * A booking enquiry — story 1.12.
 *
 * An ObjectModel, not a hand-rolled repository like GFEstablishmentRepository:
 * this table belongs to the module outright (no core table to avoid
 * overriding), and an ObjectModel is what lets AdminGfInquiriesController use
 * PrestaShop's stock HelperList/HelperForm — list, filter, sort, CSV export —
 * for free instead of reimplementing all of it by hand.
 *
 * The three static countRecentBy*() methods are the one thing an ObjectModel
 * cannot express on its own: AC-7's rate limiting needs aggregate counts, not
 * a single row.
 *
 * RETENTION (review decision, 2026-09-17): ip_address is retained for 90
 * days from date_add, for abuse-pattern analysis only — no automated purge
 * job exists yet for this table; that is tracked as separate follow-up work,
 * not something this class enforces today. Do not read this comment as a
 * guarantee that data older than 90 days has actually been deleted.
 *
 * DOMAIN LAYER (with a persistence mechanism attached — ObjectModel's own
 * pattern, not a layering violation: PrestaShop modules commonly persist
 * straight through their own ObjectModel from a front controller).
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GfInquiry extends ObjectModel
{
    const STATUS_NEW = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_QUOTED = 'quoted';
    const STATUS_WON = 'won';
    const STATUS_LOST = 'lost';

    public $id_gf_booking_inquiry;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $home_country;
    public $home_city;
    public $id_gf_partner;
    public $promo_code;
    public $dest_country;
    public $dest_country_other;
    public $id_product;
    public $travel_date;
    public $duration;
    public $adults;
    public $children;
    public $best_time_call;
    public $referral_source;
    public $message;
    public $consent_text;
    public $consent_at;
    public $status = self::STATUS_NEW;
    public $id_employee;
    public $ip_address;
    public $is_suspected_spam = false;
    public $date_add;
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'gf_booking_inquiry',
        'primary' => 'id_gf_booking_inquiry',
        'fields' => [
            'first_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 128],
            'last_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 128],
            'email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'required' => true, 'size' => 255],
            // allow_null on every optional field below: without it,
            // ObjectModel::formatValue() silently substitutes a type default
            // for null instead of storing NULL — 0 for an int (a partner id
            // of 0 does not exist, but reads like one), and, worse,
            // '0000-00-00' for travel_date, a placeholder date on every
            // enquiry that left the (optional) travel date blank.
            'phone' => ['type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 32, 'allow_null' => true],
            'home_country' => ['type' => self::TYPE_STRING, 'validate' => 'isLanguageIsoCode', 'size' => 2],
            'home_city' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128, 'allow_null' => true],
            'id_gf_partner' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true],
            'promo_code' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16, 'allow_null' => true],
            'dest_country' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100],
            'dest_country_other' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100, 'allow_null' => true],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true],
            'travel_date' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'allow_null' => true],
            'duration' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64, 'allow_null' => true],
            'adults' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'allow_null' => true],
            'children' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'allow_null' => true],
            'best_time_call' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128, 'allow_null' => true],
            'referral_source' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128, 'allow_null' => true],
            // TYPE_STRING, not TYPE_HTML: neither field is meant to carry
            // real markup — isCleanHtml here only rejects dangerous content
            // (already checked once by GFInquiryValidator too). TYPE_HTML
            // additionally runs the value through Tools::purifyHTML() on
            // every save, which HTML-entity-escapes plain text along with
            // it — "&" became the literal stored (and then re-escaped,
            // doubly so, on display) string "&amp;" for every submission.
            'message' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'],
            'consent_text' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'required' => true],
            'consent_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'ip_address' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 45],
            'is_suspected_spam' => ['type' => self::TYPE_BOOL],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * @return string[] The workflow AC-6 promises the admin inbox.
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
            self::STATUS_QUOTED,
            self::STATUS_WON,
            self::STATUS_LOST,
        ];
    }

    /**
     * How many enquiries this IP has placed recently — AC-7.
     *
     * Counts accepted rows, not attempts: a rejected submission is never
     * written, so this reads as "how much of the per-IP allowance is already
     * spent", which is exactly the number a rate limiter needs and requires
     * no separate attempts table.
     *
     * @param  string $ip
     * @param  int    $withinMinutes
     * @return int
     */
    public static function countRecentByIp($ip, $withinMinutes)
    {
        if ($ip === '') {
            return 0;
        }

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'gf_booking_inquiry`
             WHERE `ip_address` = \'' . pSQL($ip) . '\'
               AND `is_suspected_spam` = 0
               AND `date_add` > DATE_SUB(NOW(), INTERVAL ' . (int) $withinMinutes . ' MINUTE)'
        );
    }

    /**
     * @param  string $email
     * @param  int    $withinMinutes
     * @return int
     */
    public static function countRecentByEmail($email, $withinMinutes)
    {
        if ($email === '') {
            return 0;
        }

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'gf_booking_inquiry`
             WHERE `email` = \'' . pSQL($email) . '\'
               AND `is_suspected_spam` = 0
               AND `date_add` > DATE_SUB(NOW(), INTERVAL ' . (int) $withinMinutes . ' MINUTE)'
        );
    }
}
