<?php
/**
 * 2026 GF Experiences
 *
 * Turns a HOTEL establishment into a real QloApps hotel.
 *
 * A bookable hotel is four things: a htl_branch_info row, an Address carrying
 * its location, a category path, and the branch row pointing back at that
 * category. This creates all four, in the order QloApps' own installer uses.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFHotelFactory
{
    const DEFAULT_CHECK_IN = '14:00';
    const DEFAULT_CHECK_OUT = '11:00';
    const DEFAULT_RATING = 3;

    /** @var GFCategoryTreeBuilder */
    private $categoryTree;

    /** @var GFHotelRepository */
    private $repository;

    public function __construct(GFCategoryTreeBuilder $categoryTree, GFHotelRepository $repository)
    {
        $this->categoryTree = $categoryTree;
        $this->repository = $repository;
    }

    /**
     * Create or update the hotel for an establishment.
     *
     * @return int id of the hotel branch row.
     * @throws GFImportException
     */
    public function persist(GFEstablishment $establishment)
    {
        $existingId = $this->repository->findIdBySourceId($establishment->sourceId);
        $hotel = $existingId
            ? new HotelBranchInformation($existingId)
            : new HotelBranchInformation();

        $this->applyFields($hotel, $establishment, $existingId === null);

        if (!$hotel->save()) {
            throw new GFImportException('Could not save hotel "' . $establishment->name . '"');
        }

        $idHotel = (int) $hotel->id;

        // Claim the row before doing anything that can fail. A hotel without
        // its source id is invisible to reload and cleanup, so a half-built
        // one would otherwise be orphaned by the first error.
        $this->repository->tagWithSourceId($idHotel, $establishment->sourceId);

        // The category path needs the hotel to exist, and the hotel needs to
        // point at the category — hence the second save.
        $hotel->id_category = $this->categoryTree->buildPathFor($establishment);
        $hotel->save();

        $this->persistAddress($idHotel, $establishment);

        return $idHotel;
    }

    private function applyFields(HotelBranchInformation $hotel, GFEstablishment $establishment, $isNew)
    {
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];

            $hotel->hotel_name[$idLang] = $establishment->name;
            $hotel->description[$idLang] = $establishment->description;
            $hotel->short_description[$idLang] = Tools::substr(
                strip_tags($establishment->description),
                0,
                400
            );
            $hotel->policies[$idLang] = '';
        }

        if (!$isNew) {
            return;
        }

        // Only on creation, so back-office edits to these survive a re-import.
        $hotel->active = 1;
        $hotel->email = $this->buildContactEmail($establishment);
        $hotel->check_in = self::DEFAULT_CHECK_IN;
        $hotel->check_out = self::DEFAULT_CHECK_OUT;
        $hotel->rating = self::DEFAULT_RATING;
    }

    /**
     * QloApps requires an email on every hotel. The source data has none, so
     * derive a per-hotel address on the brand's own domain rather than
     * inventing a contact that might reach a real inbox.
     */
    private function buildContactEmail(GFEstablishment $establishment)
    {
        $slug = Tools::link_rewrite($establishment->name);

        return $slug . '@gf-experiences.example';
    }

    /**
     * Every hotel carries one Address, which is where its city and country
     * actually live — htl_branch_info holds no location of its own.
     */
    private function persistAddress($idHotel, GFEstablishment $establishment)
    {
        $existingId = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT `id_address` FROM `' . _DB_PREFIX_ . 'address`
             WHERE `id_hotel` = ' . (int) $idHotel . ' AND `deleted` = 0'
        );

        $address = $existingId ? new Address($existingId) : new Address();

        $address->id_hotel = (int) $idHotel;
        $address->id_country = $this->resolveCountryId($establishment->country);
        $address->id_state = $this->resolveStateId($address->id_country);
        $address->city = $establishment->city ?: $establishment->country;
        $address->address1 = $establishment->city ?: $establishment->country;
        $address->alias = Tools::substr($establishment->name, 0, 32);
        $address->lastname = Tools::substr($establishment->name, 0, 32);
        $address->firstname = Tools::substr($establishment->name, 0, 32);
        $address->postcode = $this->resolvePostcode($address->id_country);

        $address->save();
    }

    private function resolveCountryId($countryName)
    {
        $idCountry = (int) Country::getByIso($this->guessIso($countryName));

        if ($idCountry) {
            return $idCountry;
        }

        $idCountry = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT `id_country` FROM `' . _DB_PREFIX_ . 'country_lang`
             WHERE `name` = \'' . pSQL($countryName) . '\''
        );

        return $idCountry ?: (int) Configuration::get('PS_COUNTRY_DEFAULT');
    }

    /**
     * The source data names countries in English; map the ones it uses.
     */
    private function guessIso($countryName)
    {
        $map = [
            'canada' => 'CA',
            'costa rica' => 'CR',
            'spain' => 'ES',
            'usa' => 'US',
            'united states' => 'US',
            'italy' => 'IT',
        ];

        $key = Tools::strtolower(trim($countryName));

        return isset($map[$key]) ? $map[$key] : '';
    }

    /**
     * Some countries require a state on the address; supply the first one.
     */
    private function resolveStateId($idCountry)
    {
        $states = State::getStatesByIdCountry((int) $idCountry);

        return empty($states) ? 0 : (int) $states[0]['id_state'];
    }

    private function resolvePostcode($idCountry)
    {
        $format = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT `zip_code_format` FROM `' . _DB_PREFIX_ . 'country`
             WHERE `id_country` = ' . (int) $idCountry
        );

        if (!$format) {
            return '';
        }

        // zip_code_format uses N for a digit, L for a letter, C for the ISO.
        return str_replace(['N', 'L', 'C'], ['1', 'A', ''], $format);
    }
}
