<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the branded placeholder generator.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFPlaceholderImageGenerator::class)]
#[CoversClass(GFImagePlaceholder::class)]
class GFPlaceholderImageGeneratorTest extends TestCase
{
    /**
     * Summed channel difference two grounds must clear to be told apart at
     * thumbnail size. JPEG noise over a flat area is a value or two, so this
     * is comfortably above it.
     */
    const MIN_GROUND_DISTANCE = 30;

    /** @var string[] Everything generate() wrote, removed after each test. */
    private $written = [];

    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available in this PHP build.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->written = [];
    }

    #[Test]
    public function it_writes_a_jpeg_at_the_declared_size(): void
    {
        $path = $this->generate('Garden Room', '4-room-STD');

        $info = getimagesize($path);

        $this->assertSame(GFPlaceholderImageGenerator::WIDTH, $info[0]);
        $this->assertSame(GFPlaceholderImageGenerator::HEIGHT, $info[1]);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
    }

    /**
     * A re-import must not reshuffle the listing, so the same room has to draw
     * the same placeholder every time.
     */
    #[Test]
    public function the_same_key_always_draws_the_same_ground(): void
    {
        $first = $this->groundOf($this->generate('Garden Room', '4-room-STD'));
        $second = $this->groundOf($this->generate('Garden Room', '4-room-STD'));

        $this->assertSame($first, $second);
    }

    /**
     * The whole point of the feature: a hotel's room list must not look like
     * the same picture repeated. The palette is finite, so two rooms of
     * different hotels may share a ground — two rooms of the *same* hotel,
     * which appear side by side, must not. These are the real source ids from
     * data/room-types.csv; a palette change that collides them fails here.
     */
    #[Test]
    public function rooms_of_one_hotel_draw_different_grounds(): void
    {
        $siblings = [
            ['3-room-STD', '3-room-DLX'],
            ['4-room-STD', '4-room-STE'],
            ['6-room-STD', '6-room-STE'],
        ];

        foreach ($siblings as $pair) {
            $distance = $this->distanceBetween(
                $this->groundOf($this->generate('A Room', $pair[0])),
                $this->groundOf($this->generate('A Room', $pair[1]))
            );

            // Merely "not equal" is not enough: two tints a few values apart
            // read as the same picture in a 250px listing thumbnail.
            $this->assertGreaterThanOrEqual(
                self::MIN_GROUND_DISTANCE,
                $distance,
                $pair[0] . ' and ' . $pair[1] . ' are too close to tell apart.'
            );
        }
    }

    /**
     * The label is what tells the two apart at a glance, so it must be drawn.
     * Without a font the image is still produced, just untitled — check that
     * the class says which of the two it is doing.
     */
    #[Test]
    public function it_reports_whether_it_can_draw_text(): void
    {
        $withoutFont = new GFPlaceholderImageGenerator('/nonexistent/font.ttf');

        $this->assertFalse($withoutFont->canRenderText());
    }

    #[Test]
    public function it_still_produces_an_image_without_a_font(): void
    {
        $generator = new GFPlaceholderImageGenerator('/nonexistent/font.ttf', '/nonexistent/mark.png');
        $path = $generator->generate('Garden Room', '4-room-STD');
        $this->written[] = $path;

        $this->assertNotNull($path);
        $this->assertSame(IMAGETYPE_JPEG, getimagesize($path)[2]);
    }

    #[Test]
    public function a_placeholder_needs_a_label_and_a_key_to_be_worth_drawing(): void
    {
        $this->assertTrue((new GFImagePlaceholder('Garden Room', '4-room-STD'))->isDrawable());
        $this->assertFalse((new GFImagePlaceholder('', '4-room-STD'))->isDrawable());
        $this->assertFalse((new GFImagePlaceholder('Garden Room', ''))->isDrawable());
    }

    #[Test]
    public function a_placeholder_keeps_what_it_was_given(): void
    {
        $placeholder = new GFImagePlaceholder('Garden Room', '4-room-STD', 'Hacienda Guachipelin');

        $this->assertSame('Garden Room', $placeholder->label);
        $this->assertSame('4-room-STD', $placeholder->variantKey);
        $this->assertSame('Hacienda Guachipelin', $placeholder->sublabel);
    }

    /**
     * @return string Path to the generated file, cleaned up after the test.
     */
    private function generate($label, $variantKey)
    {
        $path = (new GFPlaceholderImageGenerator())->generate($label, $variantKey);

        $this->assertNotNull($path, 'The generator produced nothing.');
        $this->written[] = $path;

        return $path;
    }

    /**
     * The ground colour, sampled from a corner the mark and the text never
     * reach. JPEG is lossy, so the exact triple is not asserted on — only that
     * two samples do or do not match.
     *
     * @return int[] [r, g, b]
     */
    private function groundOf($path)
    {
        $image = imagecreatefromjpeg($path);
        $rgb = imagecolorat($image, 8, 8);
        imagedestroy($image);

        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    /**
     * @return int Summed absolute difference across the three channels.
     */
    private function distanceBetween(array $first, array $second)
    {
        return abs($first[0] - $second[0])
            + abs($first[1] - $second[1])
            + abs($first[2] - $second[2]);
    }
}
