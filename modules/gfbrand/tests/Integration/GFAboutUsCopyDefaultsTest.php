<?php
/**
 * 2026 GF Experiences
 *
 * Story 1.14: installAboutUsCopyDefaults() must be idempotent and
 * language-safe — a redeploy (uninstall/install) must never overwrite an
 * owner's already-edited About Us copy, and every seeded value must be
 * readable back with a real language id, not stuck under id_lang 0.
 *
 * gfbrand.php's own docblock on this method documents a real trap in the
 * sibling seedHeroCopy() pattern: Configuration::updateValue($key, $value,
 * false, null, $idLang) passes $idLang into the $id_shop parameter, not a
 * language selector, silently storing the default under id_lang 0. This
 * test pins that installAboutUsCopyDefaults() does NOT repeat that mistake.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/gfbrand.php';

#[CoversClass(gfbrand::class)]
class GFAboutUsCopyDefaultsTest extends TestCase
{
    const KEY = 'GFBRAND_ABOUTUS_WHOWEARE_HEADING';

    /** @var gfbrand */
    private $module;

    /** @var int */
    private $idLang;

    protected function setUp(): void
    {
        $this->module = new gfbrand();
        $this->idLang = (int) Context::getContext()->language->id;

        Configuration::deleteByName(self::KEY);
    }

    protected function tearDown(): void
    {
        Configuration::deleteByName(self::KEY);
    }

    #[Test]
    public function it_seeds_the_default_readable_under_the_real_language_id(): void
    {
        $this->callPrivate('installAboutUsCopyDefaults');

        $this->assertNotEmpty(
            Configuration::get(self::KEY, $this->idLang),
            'The seeded value must be readable with the real language id, not stuck under id_lang 0.'
        );
    }

    #[Test]
    public function it_never_overwrites_a_value_an_owner_has_already_set(): void
    {
        Configuration::updateValue(self::KEY, ['1' => 'Owner-edited heading']);
        // Guard against a shop whose default language isn't id_lang 1.
        Configuration::updateValue(self::KEY, [(string) $this->idLang => 'Owner-edited heading']);

        $this->callPrivate('installAboutUsCopyDefaults');

        $this->assertSame(
            'Owner-edited heading',
            Configuration::get(self::KEY, $this->idLang),
            'A redeploy must never overwrite copy an owner already edited.'
        );
    }

    #[Test]
    public function running_it_twice_is_a_no_op_on_the_second_run(): void
    {
        $this->callPrivate('installAboutUsCopyDefaults');
        $firstValue = Configuration::get(self::KEY, $this->idLang);

        $this->callPrivate('installAboutUsCopyDefaults');

        $this->assertSame($firstValue, Configuration::get(self::KEY, $this->idLang));
    }

    private function callPrivate($method)
    {
        $reflection = new ReflectionMethod($this->module, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->module);
    }
}
