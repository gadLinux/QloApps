<?php
/**
 * 2026 GF Experiences
 *
 * The featured-strip picker query against the real schema — story 1.17, AC-3.
 *
 * The picker is an admin surface, so unlike findListing() it must see
 * deactivated establishments too (a flagged row that gets deactivated should
 * stay unflaggeable, not disappear), but it must still never show room types
 * — they are not destinations, and a "featured" room type on the homepage
 * strip would be nonsense.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFEstablishmentRepository::class)]
class GFEstablishmentFeaturedPickerQueryTest extends TestCase
{
    /** @var GFEstablishmentRepository */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = new GFEstablishmentRepository();

        if (!$this->repository->isSchemaReady()) {
            $this->markTestSkipped('The gf_* columns are not migrated yet.');
        }

        if ($this->repository->countAll() === 0) {
            $this->markTestSkipped('No establishments are loaded.');
        }
    }

    /**
     * The picker rows carry exactly what the admin screen renders: id, name,
     * country, type, and the current flag value.
     */
    #[Test]
    public function a_picker_row_carries_everything_the_admin_screen_renders(): void
    {
        $rows = $this->repository->findAllForFeaturedPicker($this->idLang());

        $this->assertNotEmpty($rows, 'The picker must return every establishment, not a filtered subset.');

        foreach ($rows as $row) {
            foreach (['id_product', 'name', 'gf_country', 'gf_type', 'gf_featured_home'] as $field) {
                $this->assertArrayHasKey($field, $row);
            }
        }
    }

    /**
     * Room types share the importer's tagging with establishments. If they
     * leak into the picker, an admin can "feature" a room type.
     */
    #[Test]
    public function the_picker_excludes_room_types(): void
    {
        $pickerIds = array_map(static function ($row) {
            return (int) $row['id_product'];
        }, $this->repository->findAllForFeaturedPicker($this->idLang()));

        $roomTypeIds = array_map('intval', array_column(
            Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                'SELECT p.`id_product` FROM `' . _DB_PREFIX_ . 'product` p
                 WHERE p.`booking_product` = 1'
            ) ?: [],
            'id_product'
        ));

        $this->assertNotSame([], $roomTypeIds, 'Fixture check: there should be room types to exclude.');

        $this->assertSame([], array_intersect($pickerIds, $roomTypeIds), 'A room type reached the featured picker.');
    }

    /**
     * The flag value in the picker rows must agree with the raw column — the
     * whole point of the screen is to show what is live before editing it.
     */
    #[Test]
    public function the_picker_flag_agrees_with_the_raw_column(): void
    {
        foreach ($this->repository->findAllForFeaturedPicker($this->idLang()) as $row) {
            $raw = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                'SELECT `gf_featured_home` FROM `' . _DB_PREFIX_ . 'product`
                 WHERE `id_product` = ' . (int) $row['id_product']
            );

            $this->assertSame($raw, (int) $row['gf_featured_home']);
        }
    }

    /**
     * Deactivated establishments stay in the picker — the regression guard
     * for the "flagged row silently falls off the strip" failure mode.
     */
    /**
     * The fixture has no deactivated establishments, so this test creates its
     * own (a bare product row with a source id, active = 0) and removes it in
     * a finally block — the same discipline as the importer test's hand-made
     * records. Without a fixture row of its own this test would never assert
     * anything and pass vacuously.
     */
    #[Test]
    public function deactivated_establishments_stay_in_the_picker(): void
    {
        $db = Db::getInstance();
        $sourceId = 'gf-picker-test-inactive-' . uniqid();
        $idLang = $this->idLang();

        $idProduct = $this->createInactiveEstablishmentRow($sourceId, $idLang);

        try {
            $pickerIds = array_map(static function ($row) {
                return (int) $row['id_product'];
            }, $this->repository->findAllForFeaturedPicker($idLang));

            $this->assertContains(
                $idProduct,
                $pickerIds,
                'A deactivated establishment fell out of the featured picker.'
            );
        } finally {
            $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'product_lang` WHERE `id_product` = ' . (int) $idProduct);
            $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . (int) $idProduct);
        }
    }

    /**
     * Insert the minimal product row a deactivated establishment has: a
     * product_lang name plus the gf_ columns the picker queries, active = 0.
     *
     * @return int
     */
    private function createInactiveEstablishmentRow($sourceId, $idLang)
    {
        $db = Db::getInstance();

        // No explicit id_product: let AUTO_INCREMENT assign it, then read it
        // back through the unique source id (this Db has no getInsertId()).
        $db->insert('product', [
            'active' => 0,
            'booking_product' => 0,
            'gf_source_id' => $sourceId,
            'gf_type' => 'HOTEL',
            'gf_country' => 'Testland',
            'gf_featured_home' => 1,
        ]);

        $idProduct = (int) $db->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product`
             WHERE `gf_source_id` = \'' . pSQL($sourceId) . '\''
        );

        $db->insert('product_lang', [
            'id_product' => $idProduct,
            'id_lang' => $idLang,
            'name' => 'Inactive Picker Test Establishment',
            'description_short' => '',
            'link_rewrite' => 'inactive-picker-test-establishment',
        ]);

        return $idProduct;
    }

    private function idLang()
    {
        return (int) Configuration::get('PS_LANG_DEFAULT');
    }
}
