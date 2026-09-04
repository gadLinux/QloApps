<?php
/**
 * 2026 GF Experiences
 *
 * An establishment repository backed by an array instead of a database.
 *
 * It deliberately does not call parent::__construct(), so no Db connection is
 * built and the unit suite stays free of the framework. Every method the
 * listing service uses is overridden; anything else would fatal, which is the
 * behaviour we want if a new dependency appears without a test noticing.
 */

class GFFakeEstablishmentRepository extends GFEstablishmentRepository
{
    /** @var array[] Rows as the real SELECT would return them. */
    private $rows;

    /** @var array The arguments findListing() was last called with. */
    public $lastListingCall = [];

    /**
     * @param array[] $rows Each needs at least name and gf_country.
     */
    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function findCountries()
    {
        $countries = [];

        foreach ($this->rows as $row) {
            $country = isset($row['gf_country']) ? trim((string) $row['gf_country']) : '';

            if ($country !== '' && !in_array($country, $countries, true)) {
                $countries[] = $country;
            }
        }

        sort($countries);

        return $countries;
    }

    public function countListing($country = '')
    {
        return count($this->matching($country));
    }

    public function findListing($country, $limit, $offset, $idLang)
    {
        $this->lastListingCall = [
            'country' => $country,
            'limit' => $limit,
            'offset' => $offset,
            'id_lang' => $idLang,
        ];

        return array_slice($this->matching($country), $offset, $limit);
    }

    /**
     * The real query orders by name and matches country case-insensitively;
     * a fake that did neither would let a broken expectation pass.
     *
     * @return array[]
     */
    private function matching($country)
    {
        $country = trim((string) $country);

        $rows = array_values(array_filter($this->rows, function ($row) use ($country) {
            if ($country === '') {
                return true;
            }

            $rowCountry = isset($row['gf_country']) ? (string) $row['gf_country'] : '';

            return strcasecmp($rowCountry, $country) === 0;
        }));

        usort($rows, function ($a, $b) {
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $rows;
    }
}
