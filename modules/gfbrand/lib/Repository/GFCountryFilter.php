<?php
/**
 * 2026 GF Experiences
 *
 * The country filter on the establishments listing — story 1.9, FR-19.
 *
 * The pill row is DERIVED from the countries present in the catalogue, never
 * declared here. The live WordPress site hardcodes SHOW ALL · USA · CANADA ·
 * SPAIN · COSTA RICA, and the booking questionnaire's parallel hardcoded list
 * has already gone stale; adding an establishment in Portugal must add a
 * Portugal pill with no code change. That is AC-2 and it is not negotiable.
 *
 * DOMAIN LAYER — no SQL, no output, no request handling.
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFCountryFilter
{
    /** Label of the pill that clears the filter. */
    const SHOW_ALL_LABEL = 'Show All';

    /** @var string[] Canonical country names present in the data, deduped, sorted. */
    private $available;

    /** @var string Canonical spelling of the selection, or '' for show-all. */
    private $selected;

    /**
     * @param string[]    $available Countries as the catalogue spells them.
     *                    Blanks and duplicates are tolerated; the caller is a
     *                    SELECT, not a curated list.
     * @param string|null $requested Raw ?country= value from the query string.
     */
    public function __construct(array $available, $requested)
    {
        $this->available = $this->normaliseAvailable($available);
        $this->selected = $this->resolve($requested);
    }

    /**
     * True when no country narrows the listing.
     */
    public function isShowingAll()
    {
        return $this->selected === '';
    }

    /**
     * Canonical spelling of the selected country, or '' for show-all.
     *
     * Canonical means "as the data spells it": a visitor arriving on
     * ?country=costa%20rica gets Costa Rica, so the active pill lights up and
     * the heading reads correctly.
     *
     * @return string
     */
    public function getSelected()
    {
        return $this->selected;
    }

    /**
     * Whether the selection is a country we actually stock.
     *
     * False only for a hand-typed or outlived URL. The caller still renders
     * the page — with the empty state (AC-6) — rather than a 404, so the
     * visitor keeps a route back to Show All.
     */
    public function isAvailable()
    {
        return $this->selected === '' || in_array($this->selected, $this->available, true);
    }

    /**
     * @return string[] Canonical country names, alphabetical.
     */
    public function getAvailable()
    {
        return $this->available;
    }

    /**
     * The pill row: Show All first, then one pill per country.
     *
     * Exactly one pill carries active => true, which the template renders as
     * aria-pressed="true" (AC-5).
     *
     * @return array[] Each ['label' => string, 'value' => string, 'active' => bool]
     */
    public function getPills()
    {
        $pills = [[
            'label' => self::SHOW_ALL_LABEL,
            'value' => '',
            'active' => $this->isShowingAll(),
        ]];

        foreach ($this->available as $country) {
            $pills[] = [
                'label' => $country,
                'value' => $country,
                'active' => $country === $this->selected,
            ];
        }

        return $pills;
    }

    /**
     * Deduplicate, drop blanks, sort.
     *
     * Alphabetical rather than the live site's arbitrary sequence: a derived
     * list needs an ordering rule a reader can predict, and "the order rows
     * happened to come back in" is not one.
     *
     * @param  string[] $available
     * @return string[]
     */
    private function normaliseAvailable(array $available)
    {
        $countries = [];

        foreach ($available as $country) {
            $country = trim((string) $country);

            if ($country === '') {
                continue;
            }

            $countries[$this->key($country)] = $country;
        }

        $countries = array_values($countries);
        sort($countries, SORT_NATURAL | SORT_FLAG_CASE);

        return $countries;
    }

    /**
     * Match the request against the data, and answer in the data's spelling.
     *
     * An unrecognised country is kept rather than discarded: silently showing
     * everything would tell the visitor their filter worked when it did not.
     *
     * @param  string|null $requested
     * @return string
     */
    private function resolve($requested)
    {
        $requested = trim((string) $requested);

        if ($requested === '') {
            return '';
        }

        foreach ($this->available as $country) {
            if ($this->key($country) === $this->key($requested)) {
                return $country;
            }
        }

        return $requested;
    }

    /**
     * Comparison key: case- and spacing-insensitive.
     */
    private function key($country)
    {
        return Tools::strtolower(preg_replace('/\s+/u', ' ', trim((string) $country)));
    }
}
