<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for seed-image resolution.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFImageLocator::class)]
class GFImageLocatorTest extends TestCase
{
    /** @var string */
    private $tempDir;

    /** @var string */
    private $firstDir;

    /** @var string */
    private $secondDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/gf-images-' . uniqid();
        $this->firstDir = $this->tempDir . '/first';
        $this->secondDir = $this->tempDir . '/second';

        mkdir($this->firstDir, 0777, true);
        mkdir($this->secondDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->firstDir, $this->secondDir] as $directory) {
            foreach (glob($directory . '/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }

        rmdir($this->tempDir);
    }

    #[Test]
    public function it_finds_an_image_in_the_search_path(): void
    {
        $this->givenImage($this->firstDir, 'hacienda-guachupelin.jpg');

        $locator = $this->makeLocator();

        $this->assertSame(
            $this->firstDir . '/hacienda-guachupelin.jpg',
            $locator->locate('hacienda-guachupelin.jpg')
        );
    }

    #[Test]
    public function earlier_search_paths_win(): void
    {
        $this->givenImage($this->firstDir, 'shared.jpg');
        $this->givenImage($this->secondDir, 'shared.jpg');

        $this->assertSame(
            $this->firstDir . '/shared.jpg',
            $this->makeLocator()->locate('shared.jpg')
        );
    }

    #[Test]
    public function it_falls_through_to_a_later_path(): void
    {
        $this->givenImage($this->secondDir, 'only-here.jpg');

        $this->assertSame(
            $this->secondDir . '/only-here.jpg',
            $this->makeLocator()->locate('only-here.jpg')
        );
    }

    /**
     * The source file gives a path relative to where it was authored. Only
     * the file name is meaningful here, and honouring the rest would let the
     * data reach outside the search paths.
     */
    #[Test]
    public function it_uses_only_the_file_name(): void
    {
        $this->givenImage($this->firstDir, 'hotel.jpg');
        $locator = $this->makeLocator();

        $expected = $this->firstDir . '/hotel.jpg';

        $this->assertSame($expected, $locator->locate('../../uploads/establishments/hotels/hotel.jpg'));
        $this->assertSame($expected, $locator->locate('/etc/passwd/../hotel.jpg'));
    }

    #[Test]
    public function a_missing_image_resolves_to_null(): void
    {
        $this->assertNull($this->makeLocator()->locate('nowhere.jpg'));
    }

    #[Test]
    public function an_empty_name_resolves_to_null(): void
    {
        $locator = $this->makeLocator();

        $this->assertNull($locator->locate(''));
        $this->assertNull($locator->locate('   '));
    }

    /**
     * A directory that happens to match the name is not an image.
     */
    #[Test]
    public function it_ignores_a_directory_of_the_same_name(): void
    {
        mkdir($this->firstDir . '/looks-like.jpg');

        $this->assertNull($this->makeLocator()->locate('looks-like.jpg'));

        rmdir($this->firstDir . '/looks-like.jpg');
    }

    private function makeLocator(): GFImageLocator
    {
        return new GFImageLocator([$this->firstDir, $this->secondDir]);
    }

    private function givenImage(string $directory, string $fileName): void
    {
        file_put_contents($directory . '/' . $fileName, 'not really a jpeg');
    }
}
