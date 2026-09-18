<?php
/**
 * 2026 GF Experiences
 *
 * All SQL touching the establishment fields on ps_product.
 *
 * Nothing above this class writes SQL against those columns; nothing in it
 * renders output or decides policy.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFEstablishmentRepository
{
    const TABLE = 'product';

    /** @var Db */
    private $db;

    public function __construct(Db $db = null)
    {
        $this->db = $db ?: Db::getInstance();
    }

    /**
     * Product id previously imported under this source id.
     *
     * @return int|null
     */
    public function findIdBySourceId($sourceId)
    {
        $id = $this->readDb()->getValue(
            'SELECT `id_product` FROM `' . $this->table() . '`
             WHERE `gf_source_id` = \'' . pSQL($sourceId) . '\''
        );

        return $id ? (int) $id : null;
    }

    /**
     * Every product id the importer created.
     *
     * @return int[]
     */
    public function findImportedIds()
    {
        $rows = $this->readDb()->executeS(
            'SELECT `id_product` FROM `' . $this->table() . '` WHERE ' . $this->importedCondition()
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map('intval', array_column($rows, 'id_product'));
    }

    /**
     * How many establishments exist, broken down by type.
     *
     * @return array<string, int> Type => count.
     */
    public function countByType()
    {
        $rows = $this->readDb()->executeS(
            'SELECT `gf_type`, COUNT(*) AS total FROM `' . $this->table() . '`
             WHERE ' . $this->importedCondition() . '
             GROUP BY `gf_type`'
        );

        if (!is_array($rows)) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['gf_type']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countAll()
    {
        return (int) $this->readDb()->getValue(
            'SELECT COUNT(*) FROM `' . $this->table() . '` WHERE ' . $this->importedCondition()
        );
    }

    /**
     * Set the homepage featured flag to exactly the given product ids —
     * story 1.17, AC-3.
     *
     * The picker's "save" is one batch, not one write per checkbox: a flag is
     * a set, and saving it row by row would leave the strip half-updated if
     * the request dies mid-loop. Unflagging is part of the same operation —
     * "featured" means "in this set", so everything else in the catalogue
     * stops being featured. One transaction, because a half-set is exactly
     * the state the strip must never render.
     *
     * Only rows the importer created (or a hand-made product with a source
     * id) are touched: room types are excluded by definition, and the
     * catalogue is the picker's universe, so nothing outside it can drift.
     *
     * @param  int[] $idProducts
     * @return bool
     */
    public function setFeaturedHome(array $idProducts)
    {
        $idProducts = array_values(array_unique(array_map('intval', $idProducts)));

        $db = $this->db;
        $table = $this->table();

        $db->execute('START TRANSACTION');

        try {
            if ($idProducts === []) {
                $db->execute(
                    'UPDATE `' . $table . '` SET `gf_featured_home` = 0
                     WHERE ' . $this->importedCondition()
                      . ' AND `booking_product` = 0'
                );
            } else {
                $ids = implode(',', $idProducts);
                $db->execute(
                    'UPDATE `' . $table . '` SET `gf_featured_home` = 0
                     WHERE ' . $this->importedCondition()
                      . ' AND `booking_product` = 0
                       AND `id_product` NOT IN (' . $ids . ')'
                );
                $db->execute(
                    'UPDATE `' . $table . '` SET `gf_featured_home` = 1
                     WHERE `id_product` IN (' . $ids . ')'
                );
            }

            $db->execute('COMMIT');

            return true;
        } catch (Exception $e) {
            $db->execute('ROLLBACK');

            return false;
        }
    }

    /**
     * How many establishments are currently flagged for the homepage strip
     * — story 1.13. Used to decide whether a first-install default seed is
     * still needed, without caring which ones (an owner's own picks always
     * win over the seed).
     *
     * @return int
     */
    public function countFeaturedHome()
    {
        return (int) $this->readDb()->getValue(
            'SELECT COUNT(*) FROM `' . $this->table() . '`
             WHERE ' . $this->importedCondition()
              . ' AND `gf_featured_home` = 1'
        );
    }

    /**
     * Flag the establishments matching the given names for the homepage
     * strip — story 1.13's install-time default seed.
     *
     * Matches by name rather than id: unlike setFeaturedHome() (the admin
     * picker's save path, which always has real, current ids from the
     * screen the owner is looking at), this is called at install time,
     * when the only stable identifier across a catalogue re-import is the
     * establishment's own name, not whatever id it happens to have been
     * assigned this time. A name with no current match is silently
     * skipped, not an error — the seed is a best-effort default, not a
     * contract each name must exist.
     *
     * @param  string[] $names
     * @return void
     */
    public function setFeaturedHomeByName(array $names)
    {
        if ($names === []) {
            return;
        }

        $quotedNames = implode(',', array_map([$this, 'quote'], $names));

        $ids = $this->readDb()->executeS(
            'SELECT p.`id_product`
             FROM `' . $this->table() . '` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                     ON pl.`id_product` = p.`id_product`
             WHERE ' . $this->importedCondition('p') . '
               AND pl.`name` IN (' . $quotedNames . ')'
        );

        if (!is_array($ids) || $ids === []) {
            return;
        }

        $this->setFeaturedHome(array_column($ids, 'id_product'));
    }

    /**
     * Write the GF fields onto an existing product row.
     *
     * @param  int $idProduct
     * @return bool
     */
    public function saveFields($idProduct, GFEstablishment $establishment)
    {
        $assignments = [
            '`gf_source_id` = ' . $this->quote($establishment->sourceId),
            '`gf_type` = ' . $this->quote($establishment->type),
            '`gf_destination_url` = ' . $this->quote($establishment->destinationUrl),
            '`gf_certification` = ' . $this->quote($establishment->certification),
            '`gf_country` = ' . $this->quote($establishment->country),
            '`gf_city` = ' . $this->quote($establishment->city),
            '`gf_has_channel_manager` = ' . (int) $establishment->hasChannelManager,
            '`gf_channel_manager_status` = ' . $this->quote($establishment->channelManagerStatus),
            '`gf_featured_home` = ' . (int) $establishment->featuredHome,
        ];

        return (bool) $this->db->execute(
            'UPDATE `' . $this->table() . '`
             SET ' . implode(', ', $assignments) . '
             WHERE `id_product` = ' . (int) $idProduct
        );
    }

    /**
     * True once the migration has run. Guards features that read the columns
     * before the schema catches up.
     */
    public function isSchemaReady()
    {
        $count = $this->readDb()->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
               AND TABLE_NAME = \'' . pSQL($this->table()) . '\'
               AND COLUMN_NAME = \'gf_source_id\''
        );

        return (int) $count > 0;
    }

    /**
     * Every country the catalogue actually stocks, alphabetical.
     *
     * This is what makes the filter row derived rather than declared: the pill
     * list is a SELECT DISTINCT, so a Portugal establishment produces a
     * Portugal pill with no code change (story 1.9 AC-2).
     *
     * @return string[]
     */
    public function findCountries()
    {
        $rows = $this->readDb()->executeS(
            'SELECT DISTINCT `gf_country` FROM `' . $this->table() . '`
             WHERE ' . $this->listableCondition() . '
               AND `gf_country` != \'\'
             ORDER BY `gf_country` ASC'
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_column($rows, 'gf_country');
    }

    /**
     * How many establishments the listing would show under this filter.
     *
     * @param  string $country Canonical country name, or '' for all.
     * @return int
     */
    public function countListing($country = '')
    {
        return (int) $this->readDb()->getValue(
            'SELECT COUNT(*) FROM `' . $this->table() . '`
             WHERE ' . $this->listableCondition() . $this->countryCondition($country)
        );
    }

    /**
     * One page of the listing, ordered by name.
     *
     * Filtering and paging happen here, in SQL, rather than in PHP over the
     * whole catalogue: story 1.9 D6 requires the filter to work with
     * JavaScript disabled, which means the server must return only the rows
     * the visitor asked for.
     *
     * @param  string $country Canonical country name, or '' for all.
     * @param  int    $limit
     * @param  int    $offset
     * @param  int    $idLang
     * @return array[] Raw rows; the caller turns them into view models.
     */
    public function findListing($country, $limit, $offset, $idLang)
    {
        $rows = $this->readDb()->executeS(
            'SELECT p.`id_product`, p.`gf_source_id`, p.`gf_type`, p.`gf_country`, p.`gf_city`,
                    p.`gf_destination_url`, p.`gf_certification`,
                    p.`gf_has_channel_manager`, p.`gf_channel_manager_status`,
                    pl.`name`, pl.`description_short`, pl.`link_rewrite`,
                    (SELECT i.`id_image` FROM `' . _DB_PREFIX_ . 'image` i
                      WHERE i.`id_product` = p.`id_product`
                      ORDER BY i.`cover` DESC, i.`position` ASC LIMIT 1) AS `id_image`
             FROM `' . $this->table() . '` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                     ON pl.`id_product` = p.`id_product`
                    AND pl.`id_lang` = ' . (int) $idLang . '
             WHERE ' . $this->listableCondition('p') . $this->countryCondition($country, 'p') . '
             ORDER BY pl.`name` ASC
             LIMIT ' . (int) $offset . ', ' . (int) $limit
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Every establishment, every country, ordered by name — story 1.12.
     *
     * The questionnaire's establishment select is one query covering every
     * country rather than one query per selection: AC-10 requires the
     * country/establishment dependency to degrade to "show all establishments"
     * with JavaScript disabled, and the simplest way to guarantee that is to
     * never depend on JavaScript to fetch the options in the first place. The
     * front controller renders every option up front, tagged with its
     * country; a small script narrows what is visible on change, and doing
     * nothing at all is what "show all" already looks like.
     *
     * @param  int $idLang
     * @return array[] Each ['id_product' => int, 'name' => string, 'gf_country' => string].
     */
    public function findAllForSelect($idLang)
    {
        $rows = $this->readDb()->executeS(
            'SELECT p.`id_product`, pl.`name`, p.`gf_country`
             FROM `' . $this->table() . '` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                     ON pl.`id_product` = p.`id_product`
                    AND pl.`id_lang` = ' . (int) $idLang . '
             WHERE ' . $this->listableCondition('p') . '
             ORDER BY pl.`name` ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Every establishment for the back-office featured strip picker —
     * story 1.17, AC-3.
     *
     * Deliberately looser than findListing(): an editor toggling the homepage
     * flag needs to see the whole catalogue, including deactivated rows, so a
     * previously-flagged establishment cannot silently fall off the strip the
     * moment it is deactivated and the admin can never unflag it. Room types
     * are still excluded — they are not a destination.
     *
     * @param  int $idLang
     * @return array[] Raw rows; each carries id_product, name, gf_country,
     *                 gf_type and the current gf_featured_home value.
     */
    public function findAllForFeaturedPicker($idLang)
    {
        $rows = $this->readDb()->executeS(
            'SELECT p.`id_product`, p.`gf_type`, p.`gf_country`, p.`gf_featured_home`,
                    pl.`name`
             FROM `' . $this->table() . '` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                     ON pl.`id_product` = p.`id_product`
                    AND pl.`id_lang` = ' . (int) $idLang . '
             WHERE ' . $this->importedCondition('p')
               . ' AND p.`booking_product` = 0
             ORDER BY pl.`name` ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Establishments an admin has flagged for the homepage strip — story 1.13, AC-4.
     *
     * An editorial decision lives in a column, not in code: flipping the flag
     * on a different establishment changes the strip with no deploy. There is
     * no fixed count — the strip shows however many are flagged (the design is
     * four, but the query must not hardcode that).
     *
     * @param  int $idLang
     * @return array[] Same shape as findListing(), so the card decorator
     *                 serves both the listing and the homepage strip.
     */
    public function findFeaturedForHome($idLang)
    {
        $rows = $this->readDb()->executeS(
            'SELECT p.`id_product`, p.`gf_source_id`, p.`gf_type`, p.`gf_country`, p.`gf_city`,
                    p.`gf_destination_url`, p.`gf_certification`,
                    p.`gf_has_channel_manager`, p.`gf_channel_manager_status`,
                    pl.`name`, pl.`description_short`, pl.`link_rewrite`,
                    (SELECT i.`id_image` FROM `' . _DB_PREFIX_ . 'image` i
                      WHERE i.`id_product` = p.`id_product`
                      ORDER BY i.`cover` DESC, i.`position` ASC LIMIT 1) AS `id_image`
             FROM `' . $this->table() . '` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                     ON pl.`id_product` = p.`id_product`
                    AND pl.`id_lang` = ' . (int) $idLang . '
             WHERE ' . $this->listableCondition('p') . '
               AND p.`gf_featured_home` = 1
             ORDER BY p.`id_product` ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Rows the importer owns. Products created by hand have no source id and
     * are therefore never matched by a reload.
     */
    private function importedCondition($alias = '')
    {
        $prefix = $alias === '' ? '' : $alias . '.';

        return $prefix . '`gf_source_id` IS NOT NULL AND ' . $prefix . '`gf_source_id` != \'\'';
    }

    /**
     * What the establishments listing is a listing OF.
     *
     * booking_product separates the two kinds of product the importer creates:
     * an establishment is an informational record (0), a room type is bookable
     * inventory (1). Without this the listing would show "Standard Room" and
     * "Deluxe Room" as if they were establishments in their own right.
     */
    private function listableCondition($alias = '')
    {
        $prefix = $alias === '' ? '' : $alias . '.';

        return $this->importedCondition($alias)
            . ' AND ' . $prefix . '`booking_product` = 0'
            . ' AND ' . $prefix . '`active` = 1';
    }

    /**
     * Matched case-insensitively so a shared ?country=canada link still
     * filters, rather than quietly returning nothing.
     */
    private function countryCondition($country, $alias = '')
    {
        $country = trim((string) $country);

        if ($country === '') {
            return '';
        }

        $prefix = $alias === '' ? '' : $alias . '.';

        return ' AND LOWER(' . $prefix . '`gf_country`) = LOWER(\'' . pSQL($country) . '\')';
    }

    /**
     * NULL for null, a quoted escaped string otherwise. Keeps "no
     * certification" distinct from "certification recorded as empty".
     */
    private function quote($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        return '\'' . pSQL($value) . '\'';
    }

    private function table()
    {
        return _DB_PREFIX_ . self::TABLE;
    }

    private function readDb()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_);
    }
}
