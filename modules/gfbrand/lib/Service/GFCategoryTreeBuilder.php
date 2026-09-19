<?php
/**
 * 2026 GF Experiences
 *
 * Builds the Country → State → City → Hotel category path QloApps expects.
 *
 * QloApps locates hotels through the category tree, rooted at the Locations
 * category — not at Home. WkRoomSearchHelper asks the hotel's category
 * hasParent(PS_LOCATIONS_CATEGORY) before it populates anything, and the stock
 * search template then reads values it assumes are set: rooted anywhere else,
 * every room-type page dies on `1 + ""` under PHP 8.
 *
 * The four levels match AdminAddHotelController exactly, including its
 * fallback of naming the state after the city when there is no state — the
 * location autocomplete filters on depth, so the shape is not decorative.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFCategoryTreeBuilder
{
    /** @var int[] Customer groups every created category is visible to. */
    private $groupIds;

    public function __construct(array $groupIds = null)
    {
        $this->groupIds = $groupIds ?: $this->getDefaultGroupIds();
    }

    /**
     * Category id for this establishment's hotel, creating the path as needed.
     *
     * @return int
     * @throws GFImportException
     */
    public function buildPathFor(GFEstablishment $establishment)
    {
        $root = (int) Configuration::get('PS_LOCATIONS_CATEGORY');

        $countryName = $establishment->country ?: 'International';
        $cityName = $establishment->city ?: $countryName;

        $country = $this->findOrCreate($countryName, $root);
        // The source data carries no state. QloApps' own hotel form repeats
        // the city at this level in that case; matching it keeps our tree the
        // same shape as a hand-created hotel's.
        $state = $this->findOrCreate($cityName, $country);
        $city = $this->findOrCreate($cityName, $state);

        return $this->findOrCreate($establishment->name, $city);
    }

    /**
     * Reuses a category of the same name under the same parent rather than
     * creating a second one, so re-importing does not multiply the tree.
     *
     * @return int
     * @throws GFImportException
     */
    private function findOrCreate($name, $idParent)
    {
        $existing = $this->findByNameUnder($name, $idParent);

        if ($existing) {
            return $existing;
        }

        $category = new Category();
        $category->id_parent = (int) $idParent;
        $category->active = 1;

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $category->name[$idLang] = $name;
            $category->link_rewrite[$idLang] = Tools::link_rewrite($name);
        }

        if (!$category->save()) {
            throw new GFImportException('Could not create category "' . $name . '"');
        }

        $this->ensureVisibleToGroups((int) $category->id);

        return (int) $category->id;
    }

    /**
     * Category::add() may already have assigned the groups, and addGroups()
     * inserts unconditionally — so only add the ones that are missing.
     */
    private function ensureVisibleToGroups($idCategory)
    {
        $existing = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_group` FROM `' . _DB_PREFIX_ . 'category_group`
             WHERE `id_category` = ' . (int) $idCategory
        );

        $assigned = is_array($existing)
            ? array_map('intval', array_column($existing, 'id_group'))
            : [];

        $missing = array_diff($this->groupIds, $assigned);

        foreach ($missing as $idGroup) {
            Db::getInstance()->insert('category_group', [
                'id_category' => (int) $idCategory,
                'id_group' => (int) $idGroup,
            ]);
        }
    }

    /**
     * @return int|null
     */
    private function findByNameUnder($name, $idParent)
    {
        $id = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT c.`id_category`
             FROM `' . _DB_PREFIX_ . 'category` c
             INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.`id_category` = c.`id_category`
             WHERE c.`id_parent` = ' . (int) $idParent . '
               AND cl.`name` = \'' . pSQL($name) . '\''
        );

        return $id ? (int) $id : null;
    }

    /**
     * @return int[]
     */
    private function getDefaultGroupIds()
    {
        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_group` FROM `' . _DB_PREFIX_ . 'group`'
        );

        if (!is_array($rows) || empty($rows)) {
            return [1];
        }

        return array_map('intval', array_column($rows, 'id_group'));
    }
}
