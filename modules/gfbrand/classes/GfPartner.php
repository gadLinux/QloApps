<?php
/**
 * 2026 GF Experiences
 *
 * A partner organisation — story 1.11.
 *
 * An ObjectModel with a _lang table (D3): name and description are translated.
 * The logo, the site it links to and its category live on the base table.
 *
 * DOMAIN LAYER (persistence through ObjectModel, same pattern as GfAdvisor).
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GfPartner extends ObjectModel
{
    public $id_gf_partner;
    public $logo;
    public $website_url;
    public $category;
    public $position;
    public $active;
    public $date_add;
    public $date_upd;

    /* Multilang */
    public $name;
    public $description;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'gf_partner',
        'primary' => 'id_gf_partner',
        'multilang' => true,
        'fields' => [
            'logo' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255],
            'website_url' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'size' => 500],
            'category' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'active' => ['type' => self::TYPE_BOOL],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],

            /* Lang fields */
            'name' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCatalogName', 'required' => true, 'size' => 255],
            'description' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isCleanHtml'],
        ],
    ];

    /**
     * Active partners in display order — what the drawer and the /partners/
     * route both render.
     *
     * @param  int $idLang
     * @return GfPartner[]
     */
    public static function getActiveOrdered($idLang)
    {
        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_gf_partner`
             FROM `' . _DB_PREFIX_ . 'gf_partner`
             WHERE `active` = 1
             ORDER BY `position` ASC, `id_gf_partner` ASC'
        );

        $partners = [];

        if ($rows) {
            foreach ($rows as $row) {
                $partner = new GfPartner((int) $row['id_gf_partner'], (int) $idLang);
                if (Validate::isLoadedObject($partner)) {
                    $partners[] = $partner;
                }
            }
        }

        return $partners;
    }
}
