<?php
/**
 * 2026 GF Experiences
 *
 * Story 1.13, AC-4: the homepage featured strip must not be empty by
 * default, and that default must survive a catalogue re-import (a real,
 * confirmed-live incident — id_product values are not stable across one).
 * featureDefaultEstablishmentsOnFirstInstall() closes both: it seeds by
 * name (stable across a reimport, unlike id), and only when nothing is
 * already flagged, so it never overwrites an owner's own editorial pick.
 *
 * GFEstablishmentRepository::countFeaturedHome()/setFeaturedHomeByName()
 * are the two new repository primitives this depends on.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/gfbrand.php';

#[CoversClass(gfbrand::class)]
#[CoversClass(GFEstablishmentRepository::class)]
class GFFeaturedHomeDefaultSeedTest extends TestCase
{
    /** @var gfbrand */
    private $module;

    /** @var GFEstablishmentRepository */
    private $repository;

    /** @var int[] Originally-featured ids, restored in tearDown. */
    private $originallyFeatured;

    protected function setUp(): void
    {
        $this->module = new gfbrand();
        $this->repository = new GFEstablishmentRepository();

        if (!$this->repository->isSchemaReady()) {
            $this->markTestSkipped('The gf_* columns are not migrated yet.');
        }

        if ($this->repository->countAll() === 0) {
            $this->markTestSkipped('No establishments are loaded.');
        }

        $this->originallyFeatured = array_column(
            array_filter(
                $this->repository->findAllForFeaturedPicker((int) Context::getContext()->language->id),
                function ($row) {
                    return (int) $row['gf_featured_home'] === 1;
                }
            ),
            'id_product'
        );
    }

    protected function tearDown(): void
    {
        $this->repository->setFeaturedHome($this->originallyFeatured);
    }

    #[Test]
    public function it_seeds_a_default_when_nothing_is_flagged(): void
    {
        $this->repository->setFeaturedHome([]);
        $this->assertSame(0, $this->repository->countFeaturedHome());

        $this->callPrivate('featureDefaultEstablishmentsOnFirstInstall');

        $this->assertGreaterThan(
            0,
            $this->repository->countFeaturedHome(),
            'The strip must not be empty by default (AC-4).'
        );
    }

    #[Test]
    public function it_never_overwrites_an_owners_existing_pick(): void
    {
        $rows = $this->repository->findAllForFeaturedPicker((int) Context::getContext()->language->id);
        $ownerPick = [(int) $rows[0]['id_product']];
        $this->repository->setFeaturedHome($ownerPick);

        $this->callPrivate('featureDefaultEstablishmentsOnFirstInstall');

        $stillFlagged = array_column(
            array_filter(
                $this->repository->findAllForFeaturedPicker((int) Context::getContext()->language->id),
                function ($row) {
                    return (int) $row['gf_featured_home'] === 1;
                }
            ),
            'id_product'
        );

        $this->assertSame(
            $ownerPick,
            array_map('intval', $stillFlagged),
            'An owner-set flag must never be overwritten by the install-time default.'
        );
    }

    #[Test]
    public function setFeaturedHomeByName_matches_by_name_not_a_stable_id(): void
    {
        $rows = $this->repository->findAllForFeaturedPicker((int) Context::getContext()->language->id);
        $target = $rows[0];

        $this->repository->setFeaturedHome([]);
        $this->repository->setFeaturedHomeByName([$target['name']]);

        $this->assertSame(
            1,
            (int) $this->repository->countFeaturedHome()
        );
    }

    #[Test]
    public function setFeaturedHomeByName_silently_skips_a_name_with_no_match(): void
    {
        $this->repository->setFeaturedHome([]);

        $this->repository->setFeaturedHomeByName(['An Establishment That Does Not Exist']);

        $this->assertSame(0, $this->repository->countFeaturedHome());
    }

    private function callPrivate($method)
    {
        $reflection = new ReflectionMethod($this->module, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->module);
    }
}
