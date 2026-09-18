<?php
/**
 * 2026 GF Experiences
 *
 * Story 1.13, AC-5: three stock QloApps home page blocks
 * (`wkabouthotelblock`, `wkhotelfeaturesblock`, `wktestimonialblock`) carry
 * generic demo copy and have no equivalent on the real GF Experiences site.
 * disableUnusedStockBlocks() must disable each one that is currently
 * enabled, leave an already-disabled or absent module alone (idempotent —
 * a redeploy must not error on its own prior work), and never uninstall
 * them (an operator can always re-enable one by hand).
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/gfbrand.php';

#[CoversClass(gfbrand::class)]
class GFStockBlocksDisabledTest extends TestCase
{
    const MODULE_NAMES = ['wkabouthotelblock', 'wkhotelfeaturesblock', 'wktestimonialblock'];

    /** @var gfbrand */
    private $module;

    /** @var array<string, bool> Original enabled state, restored in tearDown. */
    private $originalEnabled = [];

    protected function setUp(): void
    {
        $this->module = new gfbrand();

        foreach (self::MODULE_NAMES as $name) {
            $instance = Module::getInstanceByName($name);

            if (!Validate::isLoadedObject($instance)) {
                $this->markTestSkipped($name . ' is not installed in this shop.');
            }

            $this->originalEnabled[$name] = $this->isEnabledUncached($instance);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnabled as $name => $wasEnabled) {
            $instance = Module::getInstanceByName($name);

            if (!Validate::isLoadedObject($instance)) {
                continue;
            }

            if ($wasEnabled) {
                $instance->enable();
            } else {
                $instance->disable();
            }
        }
    }

    #[Test]
    public function it_disables_every_stock_block_that_is_currently_enabled(): void
    {
        foreach (self::MODULE_NAMES as $name) {
            Module::getInstanceByName($name)->enable();
        }

        $this->callPrivate('disableUnusedStockBlocks');

        foreach (self::MODULE_NAMES as $name) {
            $instance = Module::getInstanceByName($name);

            $this->assertFalse(
                $this->isEnabledUncached($instance),
                $name . ' must be disabled once install() has run.'
            );
            $this->assertTrue(
                Validate::isLoadedObject($instance),
                $name . ' must remain installed -- disabling is not uninstalling.'
            );
        }
    }

    #[Test]
    public function running_it_again_on_already_disabled_modules_is_a_silent_no_op(): void
    {
        foreach (self::MODULE_NAMES as $name) {
            Module::getInstanceByName($name)->disable();
        }

        $this->callPrivate('disableUnusedStockBlocks');

        foreach (self::MODULE_NAMES as $name) {
            $this->assertFalse($this->isEnabledUncached(Module::getInstanceByName($name)));
        }
    }

    private function callPrivate($method)
    {
        $reflection = new ReflectionMethod($this->module, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->module);
    }

    /**
     * Module::isEnabled() is process-cached and never invalidated by
     * enable()/disable() in the same request -- unusable within a single
     * PHPUnit process that flips a module's state more than once. Read
     * ps_module_shop directly instead, exactly what disable() itself
     * deletes from.
     */
    private function isEnabledUncached(Module $module)
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT `id_module` FROM `' . _DB_PREFIX_ . 'module_shop`
             WHERE `id_module` = ' . (int) $module->id
              . ' AND `id_shop` = ' . (int) Context::getContext()->shop->id
        );
    }
}
