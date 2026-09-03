<?php
/**
 * 2026 GF Experiences
 *
 * Draws a branded stand-in for a product with no photograph.
 *
 * Most room types have no photography, and repeating the hotel's picture on
 * every one of its rooms reads as a fault. This produces something that is
 * plainly not a photograph, carries the brand, and differs per room so a
 * listing does not look duplicated.
 *
 * The ground colour is chosen deterministically from the product's source id,
 * so the same room always draws the same placeholder and a re-import does not
 * reshuffle the listing.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFPlaceholderImageGenerator
{
    /**
     * Square, because every product image type the theme declares is square:
     * a landscape placeholder would be letterboxed with white bars on each
     * one, which is exactly the accidental look this is here to avoid.
     */
    const WIDTH = 1000;
    const HEIGHT = 1000;

    /** Brand mark, sized and placed so the block below it sits optically centred. */
    const MARK_SIZE = 220;
    const MARK_TOP = 260;

    /** Baselines for the three lines of text. */
    const LABEL_BASELINE = 640;
    const SUBLABEL_BASELINE = 705;
    const NOTE_BASELINE = 785;

    /** Depth of the olive band along the foot. */
    const BAND_HEIGHT = 22;

    /** White margin around the mark, framing it as a plate. */
    const PLATE_PADDING = 26;

    /**
     * Grounds drawn from the brand palette (PRD §5.1), light enough to carry
     * dark text at AA contrast.
     *
     * @var array<int, int[]> RGB triples.
     */
    private static $grounds = [
        [232, 227, 217], // sand
        [214, 228, 196], // olive light
        [197, 213, 189], // moss
        [203, 222, 215], // eucalyptus
        [244, 237, 222], // linen
        [220, 219, 209], // stone
        [242, 227, 206], // warm clay
        [230, 214, 203], // blush clay
    ];

    /** Olive, for the band and rule. */
    private static $olive = [107, 142, 35];

    /** Dark olive, used for text so it clears AA on every ground above. */
    private static $textColour = [61, 78, 27];

    /** @var string|null Absolute path to a TTF, or null when none is present. */
    private $fontPath;

    /** @var string|null Absolute path to the brand mark, when it exists. */
    private $markPath;

    /**
     * @param string|null $fontPath Overrides font discovery; used by tests.
     * @param string|null $markPath Overrides the brand mark; used by tests.
     */
    public function __construct($fontPath = null, $markPath = null)
    {
        // Both are verified rather than trusted: GD emits a warning and draws
        // nothing when handed a path that is not there, and a placeholder with
        // no text is better than a warning in the import log.
        $this->fontPath = $fontPath !== null ? $this->readable($fontPath) : $this->findFont();
        $this->markPath = $markPath !== null ? $this->readable($markPath) : $this->findMark();
    }

    /**
     * Write a placeholder to disk.
     *
     * @param  string $label    Shown on the image, e.g. the room type name.
     * @param  string $variantKey Anything stable per product; picks the ground.
     * @param  string $sublabel Optional second line, e.g. the hotel name.
     * @return string|null Path to the generated JPEG, or null on failure.
     */
    public function generate($label, $variantKey, $sublabel = '')
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($canvas === false) {
            return null;
        }

        $this->paintGround($canvas, $variantKey);
        $this->paintBand($canvas);
        $this->paintMark($canvas);
        $this->paintText($canvas, $label, $sublabel);

        $path = tempnam(sys_get_temp_dir(), 'gf-placeholder-') . '.jpg';
        $written = imagejpeg($canvas, $path, 88);
        imagedestroy($canvas);

        return $written ? $path : null;
    }

    /**
     * True when text can be drawn. Without a font the placeholder is still
     * produced, just untitled.
     */
    public function canRenderText()
    {
        return $this->fontPath !== null;
    }

    private function paintGround($canvas, $variantKey)
    {
        $ground = self::$grounds[$this->pickVariant($variantKey)];

        imagefilledrectangle(
            $canvas,
            0,
            0,
            self::WIDTH,
            self::HEIGHT,
            imagecolorallocate($canvas, $ground[0], $ground[1], $ground[2])
        );
    }

    /**
     * A band along the foot, so the shape reads as deliberate design rather
     * than a failed image load.
     */
    private function paintBand($canvas)
    {
        $olive = imagecolorallocate($canvas, self::$olive[0], self::$olive[1], self::$olive[2]);

        imagefilledrectangle(
            $canvas,
            0,
            self::HEIGHT - self::BAND_HEIGHT,
            self::WIDTH,
            self::HEIGHT,
            $olive
        );
    }

    private function paintMark($canvas)
    {
        if ($this->markPath === null) {
            return;
        }

        $mark = @imagecreatefrompng($this->markPath);

        if ($mark === false) {
            return;
        }

        $x = (int) ((self::WIDTH - self::MARK_SIZE) / 2);

        $this->paintMarkPlate($canvas, $x);

        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas,
            $mark,
            $x,
            self::MARK_TOP,
            0,
            0,
            self::MARK_SIZE,
            self::MARK_SIZE,
            imagesx($mark),
            imagesy($mark)
        );
        imagedestroy($mark);
    }

    /**
     * A white, olive-ruled plate for the mark to sit on.
     *
     * The supplied logo is opaque white behind its artwork, despite the file
     * name, so it cannot simply be dropped onto a coloured ground. Framing it
     * deliberately reads as a design decision rather than as a stray white
     * box, and keeps working whatever artwork the client swaps in later.
     */
    private function paintMarkPlate($canvas, $markLeft)
    {
        $left = $markLeft - self::PLATE_PADDING;
        $top = self::MARK_TOP - self::PLATE_PADDING;
        $right = $markLeft + self::MARK_SIZE + self::PLATE_PADDING;
        $bottom = self::MARK_TOP + self::MARK_SIZE + self::PLATE_PADDING;

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $olive = imagecolorallocate($canvas, self::$olive[0], self::$olive[1], self::$olive[2]);

        imagefilledrectangle($canvas, $left, $top, $right, $bottom, $white);
        imagerectangle($canvas, $left, $top, $right, $bottom, $olive);
    }

    private function paintText($canvas, $label, $sublabel)
    {
        if (!$this->canRenderText()) {
            return;
        }

        $colour = imagecolorallocate(
            $canvas,
            self::$textColour[0],
            self::$textColour[1],
            self::$textColour[2]
        );

        $this->drawCentred($canvas, $label, 40, self::LABEL_BASELINE, $colour);

        if ($sublabel !== '') {
            $this->drawCentred($canvas, $sublabel, 24, self::SUBLABEL_BASELINE, $colour);
        }

        $this->drawCentred($canvas, 'Photography to follow', 19, self::NOTE_BASELINE, $colour);
    }

    /**
     * Draw one line centred horizontally, shrinking it until it fits.
     */
    private function drawCentred($canvas, $text, $size, $baseline, $colour)
    {
        $maxWidth = self::WIDTH - 120;

        while ($size > 10) {
            $box = imagettfbbox($size, 0, $this->fontPath, $text);

            if ($box === false) {
                return;
            }

            $width = abs($box[4] - $box[0]);

            if ($width <= $maxWidth) {
                imagettftext(
                    $canvas,
                    $size,
                    0,
                    (int) ((self::WIDTH - $width) / 2),
                    $baseline,
                    $colour,
                    $this->fontPath,
                    $text
                );

                return;
            }

            $size -= 2;
        }
    }

    /**
     * Stable index into the ground palette.
     */
    private function pickVariant($variantKey)
    {
        return abs(crc32((string) $variantKey)) % count(self::$grounds);
    }

    /**
     * @return string|null
     */
    private function findFont()
    {
        $candidates = [
            _PS_MODULE_DIR_ . 'gfbrand/assets/fonts/placeholder.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        ];

        foreach ($candidates as $path) {
            if ($this->readable($path) !== null) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return string|null
     */
    private function findMark()
    {
        return $this->readable(_PS_MODULE_DIR_ . 'gfbrand/assets/logo-transparent.png');
    }

    /**
     * @return string|null The path, or null when it is not a readable file.
     */
    private function readable($path)
    {
        return is_file($path) && is_readable($path) ? $path : null;
    }
}
