<?php
/**
 * 2026 GF Experiences
 *
 * Story 1.13: disabling wkabouthotelblock/wkhotelfeaturesblock/
 * wktestimonialblock (disableUnusedStockBlocks()) left their in-page
 * anchors on the header/mobile nav menu pointing at sections that no
 * longer render -- confirmed live via curl against the running homepage.
 * Those nav links are their own independent rows in
 * `blocknavigationmenu`'s ht_custom_navigation_link table, not generated
 * at render time from which blocks are active, so disabling the blocks
 * does nothing to them on its own.
 *
 * disableStaleNavigationLinks() must deactivate exactly the three links
 * that pointed at the now-disabled blocks, leave "Rooms" (wkhotelroom,
 * still active, its section still renders) untouched, and be idempotent.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/gfbrand.php';

#[CoversClass(gfbrand::class)]
class GFStaleNavigationLinksTest extends TestCase
{
    const STALE_LINKS = ['/#hotelInteriorBlock', '/#hotelAmenitiesBlock', '/#hotelTestimonialBlock'];
    const ROOMS_LINK = '/#hotelRoomsBlock';

    /** @var gfbrand */
    private $module;

    /** @var array<string, int> link => original `active` value, restored in tearDown */
    private $originalActive = [];

    protected function setUp(): void
    {
        $this->module = new gfbrand();

        if (!Db::getInstance()->getValue(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
               AND TABLE_NAME = \'' . pSQL(_DB_PREFIX_ . 'htl_custom_navigation_link') . '\''
        )) {
            $this->markTestSkipped('blocknavigationmenu is not installed in this shop.');
        }

        foreach (array_merge(self::STALE_LINKS, [self::ROOMS_LINK]) as $link) {
            $active = Db::getInstance()->getValue(
                'SELECT `active` FROM `' . _DB_PREFIX_ . 'htl_custom_navigation_link` WHERE `link` = \'' . pSQL($link) . '\''
            );

            if ($active === false) {
                $this->markTestSkipped('No nav-link row for "' . $link . '" exists in this shop.');
            }

            $this->originalActive[$link] = (int) $active;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalActive as $link => $active) {
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'htl_custom_navigation_link` SET `active` = ' . $active
                . ' WHERE `link` = \'' . pSQL($link) . '\''
            );
        }
    }

    #[Test]
    public function it_deactivates_every_stale_link_without_deleting_the_row(): void
    {
        foreach (self::STALE_LINKS as $link) {
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'htl_custom_navigation_link` SET `active` = 1 WHERE `link` = \'' . pSQL($link) . '\''
            );
        }

        $this->callPrivate('disableStaleNavigationLinks');

        foreach (self::STALE_LINKS as $link) {
            $this->assertSame(
                0,
                (int) Db::getInstance()->getValue('SELECT `active` FROM `' . _DB_PREFIX_ . 'htl_custom_navigation_link` WHERE `link` = \'' . pSQL($link) . '\''),
                'Nav link "' . $link . '" must be deactivated.'
            );
            $this->assertSame(
                1,
                (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_custom_navigation_link` WHERE `link` = \'' . pSQL($link) . '\''),
                'The row must never be deleted -- an editor must be able to re-enable it by hand.'
            );
        }
    }

    #[Test]
    public function it_leaves_the_rooms_link_untouched(): void
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'htl_custom_navigation_link` SET `active` = 1 WHERE `link` = \'' . pSQL(self::ROOMS_LINK) . '\''
        );

        $this->callPrivate('disableStaleNavigationLinks');

        $this->assertSame(
            1,
            (int) Db::getInstance()->getValue('SELECT `active` FROM `' . _DB_PREFIX_ . 'htl_custom_navigation_link` WHERE `link` = \'' . pSQL(self::ROOMS_LINK) . '\''),
            'wkhotelroom is still active and its section still renders -- its nav link must not be touched.'
        );
    }

    #[Test]
    public function running_it_again_is_a_silent_no_op(): void
    {
        $this->callPrivate('disableStaleNavigationLinks');
        $this->callPrivate('disableStaleNavigationLinks');

        foreach (self::STALE_LINKS as $link) {
            $this->assertSame(
                0,
                (int) Db::getInstance()->getValue('SELECT `active` FROM `' . _DB_PREFIX_ . 'htl_custom_navigation_link` WHERE `link` = \'' . pSQL($link) . '\'')
            );
        }
    }

    private function callPrivate($method)
    {
        $reflection = new ReflectionMethod($this->module, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->module);
    }
}
