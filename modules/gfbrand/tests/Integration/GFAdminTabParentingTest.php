<?php
/**
 * 2026 GF Experiences
 *
 * Regression test for a real bug: installPartnersTab() referenced a parent
 * tab class, 'AdminParentStats', that does not exist in this QloApps
 * install. Tab::getIdFromClassName() silently returns 0 for an unknown
 * class name rather than erroring, so the row was created with
 * id_parent = NULL — "GF Partners" rendered as an orphaned top-level
 * sidebar item instead of nesting under Customers like its sibling tabs.
 *
 * installAdvisorsTab() had a related but different problem: its code named
 * 'AdminCustomers' (a leaf tab one level below the actual "Customers" menu),
 * which PrestaShop's sidebar does not render at that depth for module tabs.
 * The live row only looked correct because it predated that edit and was
 * never recreated — installTab() only sets id_parent when the row doesn't
 * exist yet, so a parent-class correction in code is a silent no-op until
 * the tab is deleted and reinstalled.
 *
 * This test forces a fresh creation of both tabs (deleting any existing row
 * first) so it actually exercises installTab()'s parenting logic, not
 * whatever a previous install happened to leave behind.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/gfbrand.php';

#[CoversClass(gfbrand::class)]
class GFAdminTabParentingTest extends TestCase
{
    /** @var gfbrand */
    private $module;

    protected function setUp(): void
    {
        $this->module = new gfbrand();

        $this->deleteTab('AdminGfAdvisors');
        $this->deleteTab('AdminGfPartners');
    }

    protected function tearDown(): void
    {
        // Leave both tabs re-created with the fixed, correct parent rather
        // than missing — install() itself will no-op on these going forward
        // since the rows now exist.
        $this->deleteTab('AdminGfAdvisors');
        $this->deleteTab('AdminGfPartners');
        $this->callPrivate('installAdvisorsTab');
        $this->callPrivate('installPartnersTab');
    }

    #[Test]
    public function partners_tab_is_parented_under_customers_not_orphaned(): void
    {
        $this->callPrivate('installPartnersTab');

        $idParent = (int) Tab::getIdFromClassName('AdminGfPartners')
            ? $this->idParentOf('AdminGfPartners')
            : null;

        $this->assertNotNull($idParent, 'AdminGfPartners must have been created.');
        $this->assertGreaterThan(0, $idParent, 'GF Partners must not be an orphaned top-level tab.');
        $this->assertSame(
            (int) Tab::getIdFromClassName('AdminParentCustomer'),
            $idParent,
            'GF Partners must nest under the Customers menu, same as GF Advisors and GF Enquiries.'
        );
    }

    #[Test]
    public function advisors_tab_is_parented_under_the_top_level_customers_menu(): void
    {
        $this->callPrivate('installAdvisorsTab');

        $this->assertSame(
            (int) Tab::getIdFromClassName('AdminParentCustomer'),
            $this->idParentOf('AdminGfAdvisors'),
            'GF Advisors must nest directly under AdminParentCustomer, not the AdminCustomers leaf tab.'
        );
    }

    private function callPrivate($method)
    {
        $reflection = new ReflectionMethod($this->module, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->module);
    }

    private function idParentOf($className)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT `id_parent` FROM `' . _DB_PREFIX_ . 'tab` WHERE `class_name` = \'' . pSQL($className) . '\''
        );
    }

    private function deleteTab($className)
    {
        $idTab = (int) Tab::getIdFromClassName($className);

        if ($idTab) {
            $tab = new Tab($idTab);
            $tab->delete();
        }
    }
}
