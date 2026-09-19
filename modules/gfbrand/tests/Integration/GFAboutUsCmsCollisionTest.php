<?php
/**
 * 2026 GF Experiences
 *
 * Story 1.14, I/O matrix row 5: the `module-gfbrand-aboutus` route
 * (rule "about-us") claims the same URL the QloApps demo catalogue ships
 * as an active CMS page (link_rewrite "about-us"). Left both active, the
 * two would race for one URL. deactivateConflictingAboutUsCmsPage() must
 * deactivate that CMS row deterministically, matched by rewrite (not a
 * hardcoded id, since a customer's own catalogue could have renumbered
 * it), and never delete the row, and be a silent no-op when no such page
 * exists or it is already inactive.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/gfbrand.php';

#[CoversClass(gfbrand::class)]
class GFAboutUsCmsCollisionTest extends TestCase
{
    const REWRITE = 'about-us';

    /** @var gfbrand */
    private $module;

    /** @var int|null id_cms of the row this test found/created, restored in tearDown */
    private $idCms;

    /** @var int original `active` value, restored in tearDown */
    private $originalActive;

    protected function setUp(): void
    {
        $this->module = new gfbrand();

        $this->idCms = (int) Db::getInstance()->getValue(
            'SELECT cl.id_cms FROM `' . _DB_PREFIX_ . 'cms_lang` cl WHERE cl.link_rewrite = \'' . pSQL(self::REWRITE) . '\''
        );

        if (!$this->idCms) {
            $this->markTestSkipped('No CMS page with link_rewrite "about-us" exists in this install.');
        }

        $this->originalActive = (int) Db::getInstance()->getValue(
            'SELECT `active` FROM `' . _DB_PREFIX_ . 'cms` WHERE `id_cms` = ' . $this->idCms
        );
    }

    protected function tearDown(): void
    {
        if ($this->idCms) {
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'cms` SET `active` = ' . (int) $this->originalActive
                . ' WHERE `id_cms` = ' . $this->idCms
            );
        }
    }

    #[Test]
    public function it_deactivates_the_conflicting_cms_page_without_deleting_it(): void
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'cms` SET `active` = 1 WHERE `id_cms` = ' . $this->idCms
        );

        $this->callPrivate('deactivateConflictingAboutUsCmsPage');

        $this->assertSame(
            0,
            (int) Db::getInstance()->getValue('SELECT `active` FROM `' . _DB_PREFIX_ . 'cms` WHERE `id_cms` = ' . $this->idCms),
            'The conflicting CMS page must be deactivated, not left active alongside the module route.'
        );
        $this->assertSame(
            1,
            (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'cms` WHERE `id_cms` = ' . $this->idCms),
            'The row must never be deleted -- an editor must be able to re-enable it by hand.'
        );
    }

    #[Test]
    public function running_it_again_on_an_already_inactive_page_is_a_silent_no_op(): void
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'cms` SET `active` = 0 WHERE `id_cms` = ' . $this->idCms
        );

        $this->callPrivate('deactivateConflictingAboutUsCmsPage');

        $this->assertSame(
            0,
            (int) Db::getInstance()->getValue('SELECT `active` FROM `' . _DB_PREFIX_ . 'cms` WHERE `id_cms` = ' . $this->idCms)
        );
    }

    private function callPrivate($method)
    {
        $reflection = new ReflectionMethod($this->module, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->module);
    }
}
