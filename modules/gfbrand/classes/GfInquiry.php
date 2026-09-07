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
            'phone' => ['type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 32],
            'home_country' => ['type' => self::TYPE_STRING, 'validate' => 'isLanguageIsoCode', 'size' => 2],
            'home_city' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128],
            'id_gf_partner' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'promo_code' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],
            'dest_country' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'travel_date' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'duration' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'adults' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'children' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'best_time_call' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128],
            'referral_source' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128],
            'message' => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'],
            'consent_text' => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml', 'required' => true],
            'consent_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'ip_address' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 45],
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
               AND `date_add` > DATE_SUB(NOW(), INTERVAL ' . (int) $withinMinutes . ' MINUTE)'
        );
    }
}
