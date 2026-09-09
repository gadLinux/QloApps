<?php
/**
 * 2026 GF Experiences
 *
 * Loads advisors and partners from the seed files — story 1.11, AC-2.
 *
 * Two modes, mirroring the establishments importer:
 *   import()  upsert. Rows are matched on their source id, so a re-run updates
 *             what it created instead of duplicating it, and back-office edits
 *             to other fields survive.
 *   reload()  delete every record this importer created, then import. Records
 *             created by hand in the back office are left alone.
 *
 * The source files carry one language only (the client authored them in EN),
 * so an imported record is written to every active language: an advisor must
 * not disappear in the ES storefront just because nobody typed an Spanish
 * name. A back-office edit in one language still overrides, because it is a
 * separate _lang row.
 *
 * APPLICATION LAYER — orchestrates the reader and the ObjectModels.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFAdvisorPartnerImporter
{
    /** @var GFAdvisorPartnerCsvReader */
    private $reader;

    public function __construct(GFAdvisorPartnerCsvReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @param  string $path
     * @return GFImportResult
     */
    public function importAdvisors($path)
    {
        $result = new GFImportResult();
        $seeds = $this->reader->readAdvisors($path);
        $result->addErrors($this->reader->getErrors());

        if ($result->getErrors()) {
            return $result;
        }

        foreach ($seeds as $seed) {
            $this->saveAdvisor($seed, $result);
        }

        return $result;
    }

    /**
     * @param  string $path
     * @return GFImportResult
     */
    public function importPartners($path)
    {
        $result = new GFImportResult();
        $seeds = $this->reader->readPartners($path);
        $result->addErrors($this->reader->getErrors());

        if ($result->getErrors()) {
            return $result;
        }

        foreach ($seeds as $seed) {
            $this->savePartner($seed, $result);
        }

        return $result;
    }

    /**
     * @param  string $advisorPath
     * @param  string $partnerPath
     * @return GFImportResult
     */
    public function reload($advisorPath, $partnerPath)
    {
        $this->deleteImported();

        $result = $this->importAdvisors($advisorPath);
        $result->addErrors($this->importPartners($partnerPath)->getErrors());

        return $result;
    }

    private function saveAdvisor(GFAdvisorSeed $seed, GFImportResult $result)
    {
        $id = $this->findAdvisorId($seed->sourceId);

        $advisor = $id ? new GfAdvisor($id) : new GfAdvisor();
        $advisor->phone = $seed->phone !== '' ? $seed->phone : null;
        $advisor->phone_e164 = $seed->phoneE164 !== '' ? $seed->phoneE164 : null;
        $advisor->website_url = $seed->websiteUrl !== '' ? $seed->websiteUrl : null;
        $advisor->email = $seed->email !== '' ? $seed->email : null;
        $advisor->image = $seed->imageUrl !== '' ? $seed->imageUrl : null;
        $advisor->position = $seed->position;
        $advisor->active = (int) $seed->active;

        // Multilang fields are per-language arrays (ObjectModel 1.6): the seed
        // file carries one language, so every active language gets the same
        // value, and a later back-office edit in one language still wins.
        $advisor->name = $this->forAllLanguages($seed->name);
        $advisor->regions_served = $seed->regionsServed !== '' ? $this->forAllLanguages($seed->regionsServed) : null;
        $advisor->bio = $seed->bio !== '' ? $this->forAllLanguages($seed->bio) : null;

        if ($id && !$advisor->update()) {
            $result->recordFailure('advisor ' . $seed->sourceId . ': could not update');
            return;
        }

        if (!$id && !$advisor->add()) {
            $result->recordFailure('advisor ' . $seed->sourceId . ': could not create');
            return;
        }

        // ObjectModel 1.6 stores the new id in the generic $this->id, not in
        // the typed property.
        $this->recordSourceId('advisor', (int) $advisor->id, $seed->sourceId);
        $id ? $result->recordUpdated() : $result->recordCreated();
    }

    private function savePartner(GFPartnerSeed $seed, GFImportResult $result)
    {
        $id = $this->findPartnerId($seed->sourceId);

        $partner = $id ? new GfPartner($id) : new GfPartner();
        $partner->logo = $seed->logoFile !== '' ? $seed->logoFile : null;
        $partner->website_url = $seed->websiteUrl !== '' ? $seed->websiteUrl : null;
        $partner->category = $seed->category !== '' ? $seed->category : null;
        $partner->position = $seed->position;
        $partner->active = (int) $seed->active;
        $partner->name = $this->forAllLanguages($seed->name);
        $partner->description = $seed->description !== '' ? $this->forAllLanguages($seed->description) : null;

        if ($id && !$partner->update()) {
            $result->recordFailure('partner ' . $seed->sourceId . ': could not update');
            return;
        }

        if (!$id && !$partner->add()) {
            $result->recordFailure('partner ' . $seed->sourceId . ': could not create');
            return;
        }

        $this->recordSourceId('partner', (int) $partner->id, $seed->sourceId);
        $id ? $result->recordUpdated() : $result->recordCreated();
    }

    /**
     * One value into every active language — the shape ObjectModel wants for
     * a lang field when no id_lang is in play.
     *
     * @param  string $value
     * @return array<int, string>
     */
    private function forAllLanguages($value)
    {
        $languages = [];

        foreach (Language::getIDs(false) as $idLang) {
            $languages[(int) $idLang] = $value;
        }

        return $languages;
    }

    /**
     * A record is "imported" only if it carries a source id the file could
     * have written. Hand-created records have none and survive a reload.
     */
    private function deleteImported()
    {
        foreach (['advisor', 'partner'] as $kind) {
            $table = $kind === 'advisor' ? 'gf_advisor' : 'gf_partner';
            $sourceTable = $table . '_source';

            $rows = Db::getInstance()->executeS(
                'SELECT `id_' . $table . '` FROM `' . _DB_PREFIX_ . $sourceTable . '`'
            );

            if (!$rows) {
                continue;
            }

            $ids = array_map(function ($row) use ($table) {
                return (int) $row['id_' . $table];
            }, $rows);

            $idList = implode(', ', $ids);
            $class = $kind === 'advisor' ? 'GfAdvisor' : 'GfPartner';

            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . $table . '` WHERE `id_' . $table . '` IN (' . $idList . ')'
            );
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . $table . '_lang` WHERE `id_' . $table . '` IN (' . $idList . ')'
            );
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . $sourceTable . '` WHERE `id_' . $table . '` IN (' . $idList . ')'
            );
        }
    }

    /**
     * The source id is not a column on the base table: ObjectModel refuses to
     * persist a field it does not own, and the admin form must not show it.
     * It lives in a side table the importers own outright, which is also what
     * makes "which rows did the import create" a single query.
     */
    private function recordSourceId($kind, $id, $sourceId)
    {
        $table = $kind === 'advisor' ? 'gf_advisor' : 'gf_partner';
        $sourceTable = $table . '_source';
        $column = 'id_' . $table;

        Db::getInstance()->execute(
            'REPLACE INTO `' . _DB_PREFIX_ . $sourceTable . '` (`' . $column . '`, `source_id`)
             VALUES (' . (int) $id . ', \'' . pSQL($sourceId) . '\')'
        );
    }

    private function findAdvisorId($sourceId)
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT `id_gf_advisor` FROM `' . _DB_PREFIX_ . 'gf_advisor_source`
             WHERE `source_id` = \'' . pSQL($sourceId) . '\''
        );
    }

    private function findPartnerId($sourceId)
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT `id_gf_partner` FROM `' . _DB_PREFIX_ . 'gf_partner_source`
             WHERE `source_id` = \'' . pSQL($sourceId) . '\''
        );
    }
}
