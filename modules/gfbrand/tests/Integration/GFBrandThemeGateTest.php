<?php
/**
 * 2026 GF Experiences
 *
 * gfbrand::isBrandThemeActive()/isBrandActive() against the real ps_shop and
 * ps_theme tables — story 1.20.
 *
 * This is the load-bearing contract behind "switching the active theme turns
 * GF branding on and off": it pins that the answer really does flip when
 * ps_shop.id_theme changes, using the real Theme ObjectModel and the real
 * shop row, not a fake.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/gfbrand.php';

#[CoversClass(gfbrand::class)]
class GFBrandThemeGateTest extends TestCase
{
    /** @var int The shop's id_theme as this test found it, restored afterward. */
    private $originalIdTheme;

    protected function setUp(): void
    {
        if (!Theme::getByDirectory(gfbrand::BRAND_THEME_DIRECTORY)) {
            $this->markTestSkipped('The gfexperiences theme is not registered on this install.');
        }

        $this->originalIdTheme = (int) Db::getInstance()->getValue(
            'SELECT `id_theme` FROM `' . _DB_PREFIX_ . 'shop` WHERE `id_shop` = ' . (int) Context::getContext()->shop->id
        );
    }

    protected function tearDown(): void
    {
        $this->setShopTheme($this->originalIdTheme);
    }

    #[Test]
    public function the_brand_theme_is_active_when_gfexperiences_is_the_shop_theme(): void
    {
        $this->setShopTheme($this->brandThemeId());

        $this->assertTrue(gfbrand::isBrandThemeActive());
    }

    #[Test]
    public function the_brand_theme_is_not_active_when_a_different_theme_is_the_shop_theme(): void
    {
        $otherThemeId = (int) Db::getInstance()->getValue(
            'SELECT `id_theme` FROM `' . _DB_PREFIX_ . 'theme` WHERE `directory` != \''
            . pSQL(gfbrand::BRAND_THEME_DIRECTORY) . '\''
        );

        $this->assertGreaterThan(0, $otherThemeId, 'Fixture check: a non-brand theme must exist to compare against.');

        $this->setShopTheme($otherThemeId);

        $this->assertFalse(gfbrand::isBrandThemeActive());
    }

    #[Test]
    public function is_brand_active_requires_both_the_enabled_flag_and_the_theme(): void
    {
        $originalEnabled = Configuration::get(gfbrand::CONFIG_PREFIX . 'ENABLED');

        $this->setShopTheme($this->brandThemeId());

        try {
            Configuration::updateValue(gfbrand::CONFIG_PREFIX . 'ENABLED', 0);
            $this->assertFalse(gfbrand::isBrandActive(), 'Manual disable must still suppress the brand even on its own theme.');

            Configuration::updateValue(gfbrand::CONFIG_PREFIX . 'ENABLED', 1);
            $this->assertTrue(gfbrand::isBrandActive());
        } finally {
            Configuration::updateValue(gfbrand::CONFIG_PREFIX . 'ENABLED', $originalEnabled);
        }
    }

    /**
     * @return int id_theme of the registered gfexperiences row.
     */
    private function brandThemeId()
    {
        $theme = Theme::getByDirectory(gfbrand::BRAND_THEME_DIRECTORY);

        return $theme ? (int) $theme->id : 0;
    }

    /**
     * Sets ps_shop.id_theme directly (bypassing Shop::save()'s wider side
     * effects) and clears gfbrand's per-request cache so the next
     * isBrandThemeActive() call re-reads it — mirroring how a fresh request
     * would see the change with no cache warm from a previous test.
     *
     * @param int $idTheme
     */
    private function setShopTheme($idTheme)
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'shop` SET `id_theme` = ' . (int) $idTheme
            . ' WHERE `id_shop` = ' . (int) Context::getContext()->shop->id
        );

        Context::getContext()->shop->id_theme = (int) $idTheme;

        $cacheProperty = new ReflectionProperty('gfbrand', 'themeActiveCache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null, null);
    }
}
