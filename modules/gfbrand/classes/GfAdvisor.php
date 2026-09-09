<?php
/**
 * 2026 GF Experiences
 *
 * A travel advisor — story 1.11.
 *
 * An ObjectModel with a _lang table (D3): name, regions served and bio are
 * translated, and it is the multilang shape that gives the admin its language
 * tabs and the front controller per-language resolution for free.
 *
 * phone_e164 is the machine-readable dial form of phone: the card builds its
 * tel: link from it, never from the display number, which carries spaces and
 * dashes for humans.
 *
 * DOMAIN LAYER (persistence through ObjectModel, same pattern as GfInquiry).
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GfAdvisor extends ObjectModel
{
    public $id_gf_advisor;
    public $phone;
    public $phone_e164;
    public $website_url;
    public $email;
    public $image;
    public $position;
    public $active;
    public $date_add;
    public $date_upd;

    /* Multilang */
    public $name;
    public $regions_served;
    public $bio;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'gf_advisor',
        'primary' => 'id_gf_advisor',
        'multilang' => true,
        'fields' => [
            'phone' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32],
            'phone_e164' => ['type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 20],
            'website_url' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'size' => 500],
            'email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 255],
            'image' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'active' => ['type' => self::TYPE_BOOL],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],

            /* Lang fields */
            'name' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCatalogName', 'required' => true, 'size' => 255],
            'regions_served' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 255],
            'bio' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isCleanHtml'],
        ],
    ];

    /**
     * Active advisors in display order — what the drawer and the /advisors/
     * route both render.
     *
     * @param  int $idLang
     * @return GfAdvisor[]
     */
    public static function getActiveOrdered($idLang)
    {
        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_gf_advisor`
             FROM `' . _DB_PREFIX_ . 'gf_advisor`
             WHERE `active` = 1
             ORDER BY `position` ASC, `id_gf_advisor` ASC'
        );

        $advisors = [];

        if ($rows) {
            foreach ($rows as $row) {
                $advisor = new GfAdvisor((int) $row['id_gf_advisor'], (int) $idLang);
                if (Validate::isLoadedObject($advisor)) {
                    $advisors[] = $advisor;
                }
            }
        }

        return $advisors;
    }
}
