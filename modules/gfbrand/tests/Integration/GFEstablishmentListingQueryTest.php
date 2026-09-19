<?php
/**
 * 2026 GF Experiences
 *
 * The listing query against the real schema — story 1.9.
 *
 * The unit suite pins the listing's policy against a fake repository. This
 * file pins the one thing a fake cannot: that the SQL selects the right rows
 * out of the real ps_product, where establishments and room types live side by
 * side under the same gf_source_id mechanism.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFEstablishmentRepository::class)]
class GFEstablishmentListingQueryTest extends TestCase
{
    /** @var GFEstablishmentRepository */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = new GFEstablishmentRepository();

        if (!$this->repository->isSchemaReady()) {
            $this->markTestSkipped('The gf_* columns are not migrated yet.');
        }

        if ($this->repository->countListing() === 0) {
            $this->markTestSkipped('No establishments are loaded.');
        }
    }

    /**
     * The regression this file exists for.
     *
     * Room types are products too, and the importer tags them with a
     * gf_source_id exactly as it tags establishments — so "everything the
     * importer created" is the wrong set for this page. A room type leaking
     * in shows "Standard Room" on the listing as though it were somewhere you
     * could go.
     */
    #[Test]
    public function the_listing_excludes_room_types(): void
    {
        $names = $this->allListedNames();

        $roomTypeNames = array_column(
            Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                'SELECT pl.`name` FROM `' . _DB_PREFIX_ . 'product` p
                 INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                         ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $this->idLang() . '
                 WHERE p.`booking_product` = 1'
            ) ?: [],
            'name'
        );

        $this->assertNotEmpty($roomTypeNames, 'Fixture check: there should be room types to exclude.');

        foreach ($roomTypeNames as $roomType) {
            $this->assertNotContains(
                $roomType,
                $names,
                'A bookable room type reached the establishments listing.'
            );
        }
    }

    /**
     * Every pill filters to at least one card. A pill that selects nothing is
     * the hardcoded-list failure mode wearing a derived list's clothes.
     */
    #[Test]
    public function every_derived_country_has_at_least_one_establishment(): void
    {
        $countries = $this->repository->findCountries();

        $this->assertNotEmpty($countries);

        foreach ($countries as $country) {
            $this->assertGreaterThan(
                0,
                $this->repository->countListing($country),
                'The pill for ' . $country . ' would show an empty grid.'
            );
        }
    }

    /**
     * The country filter must reach SQL, and its parts must add up: the
     * per-country counts partition the whole listing.
     */
    #[Test]
    public function the_country_counts_partition_the_listing(): void
    {
        $total = 0;

        foreach ($this->repository->findCountries() as $country) {
            $total += $this->repository->countListing($country);
        }

        $this->assertSame(
            $this->repository->countListing(),
            $total,
            'Either an establishment has no country, or one is counted twice.'
        );
    }

    #[Test]
    public function a_lowercase_country_matches_the_same_rows(): void
    {
        $country = $this->repository->findCountries()[0];

        $this->assertSame(
            $this->repository->countListing($country),
            $this->repository->countListing(Tools::strtolower($country)),
            'A shared lowercase ?country= link must not return an empty page.'
        );
    }

    /**
     * AC-7: paging must walk the whole set without repeating or dropping one.
     */
    #[Test]
    public function paging_covers_every_establishment_exactly_once(): void
    {
        $total = $this->repository->countListing();
        $perPage = GFEstablishmentListing::PER_PAGE;

        $seen = [];

        for ($offset = 0; $offset < $total; $offset += $perPage) {
            foreach ($this->repository->findListing('', $perPage, $offset, $this->idLang()) as $row) {
                $seen[] = (int) $row['id_product'];
            }
        }

        $this->assertCount($total, $seen, 'Paging dropped or duplicated a row.');
        $this->assertSame(count($seen), count(array_unique($seen)));
    }

    #[Test]
    public function a_listed_row_carries_everything_a_card_renders(): void
    {
        $row = $this->repository->findListing('', 1, 0, $this->idLang())[0];

        foreach (['id_product', 'name', 'description_short', 'link_rewrite', 'gf_type', 'gf_country'] as $field) {
            $this->assertArrayHasKey($field, $row);
        }
    }

    /**
     * @return string[] Names across every page.
     */
    private function allListedNames()
    {
        $rows = $this->repository->findListing('', $this->repository->countListing(), 0, $this->idLang());

        return array_column($rows, 'name');
    }

    private function idLang()
    {
        return (int) Configuration::get('PS_LANG_DEFAULT');
    }
}
