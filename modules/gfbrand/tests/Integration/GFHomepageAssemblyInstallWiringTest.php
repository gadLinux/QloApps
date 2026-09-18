<?php
/**
 * 2026 GF Experiences
 *
 * Story 1.13 review finding (verification-gap): GFStockBlocksDisabledTest,
 * GFStaleNavigationLinksTest and GFFeaturedHomeDefaultSeedTest all call
 * their target private methods directly via reflection — proving each
 * method works, but never that gfbrand::install() actually calls them. If
 * any of the three call sites were lost in a future edit, none of those
 * tests would notice, and a fresh install would silently regress AC-4/AC-5
 * exactly as they existed before this story.
 *
 * Same precedent as GFAboutUsInstallWiringTest (story 1.14): install()
 * runs a full, live-state-mutating cycle no test in this suite invokes for
 * real, so this pins the wiring by reading install()'s own source rather
 * than running that cycle inside the automated suite.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/gfbrand.php';

#[CoversClass(gfbrand::class)]
class GFHomepageAssemblyInstallWiringTest extends TestCase
{
    #[Test]
    public function install_calls_disableUnusedStockBlocks(): void
    {
        $this->assertStringContainsString(
            '$this->disableUnusedStockBlocks();',
            $this->installMethodSource()
        );
    }

    #[Test]
    public function install_calls_disableStaleNavigationLinks(): void
    {
        $this->assertStringContainsString(
            '$this->disableStaleNavigationLinks();',
            $this->installMethodSource()
        );
    }

    #[Test]
    public function install_calls_featureDefaultEstablishmentsOnFirstInstall(): void
    {
        $this->assertStringContainsString(
            '$this->featureDefaultEstablishmentsOnFirstInstall();',
            $this->installMethodSource()
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
