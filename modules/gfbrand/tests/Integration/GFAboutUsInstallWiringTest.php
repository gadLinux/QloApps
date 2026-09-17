<?php
/**
 * 2026 GF Experiences
 *
 * Story 1.14 review finding (verification-gap): GFAboutUsCopyDefaultsTest
 * and GFAboutUsCmsCollisionTest both call installAboutUsCopyDefaults()/
 * deactivateConflictingAboutUsCmsPage() directly via reflection — proving
 * the methods work, but never that gfbrand::install() actually calls them.
 * If either call site were lost in a future edit, neither test would
 * notice, and a fresh install would silently ship empty About Us copy
 * and/or leave the demo CMS page racing the new route.
 *
 * gfbrand::install() runs a full, live-state-mutating cycle (hooks, tabs,
 * migrations, catalogue import) that no test in this suite invokes for
 * real — see GFAdminTabParentingTest/GFBrandThemeGateTest, which both
 * exercise narrower private methods instead, for the same reason. This
 * test follows that precedent: it pins the wiring by reading install()'s
 * own source rather than running the full, unsafe-to-repeat install cycle
 * inside the automated suite.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/gfbrand.php';

#[CoversClass(gfbrand::class)]
class GFAboutUsInstallWiringTest extends TestCase
{
    #[Test]
    public function install_calls_installAboutUsCopyDefaults(): void
    {
        $this->assertStringContainsString(
            '$this->installAboutUsCopyDefaults();',
            $this->installMethodSource(),
            'install() must seed the About Us page\'s default copy.'
        );
    }

    #[Test]
    public function install_calls_deactivateConflictingAboutUsCmsPage(): void
    {
        $this->assertStringContainsString(
            '$this->deactivateConflictingAboutUsCmsPage();',
            $this->installMethodSource(),
            'install() must resolve the About Us / demo CMS page URL collision.'
        );
    }

    private function installMethodSource()
    {
        $reflection = new ReflectionMethod('gfbrand', 'install');
        $file = new SplFileObject($reflection->getFileName());
        $file->seek($reflection->getStartLine() - 1);

        $source = '';
        while ($file->key() < $reflection->getEndLine()) {
            $source .= $file->current();
            $file->next();
        }

        return $source;
    }
}
