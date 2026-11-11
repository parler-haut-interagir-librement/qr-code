<?php

declare(strict_types=1);

namespace Phil\QrCode;

use DeflateContext;
use GdImage;
use Phil\QrCode\Exception\InvalidOption;

/**
 * A QR code as a PNG image, written by the library itself: a square of `$scale` pixels per module with the quiet
 * zone around the code, in two colours on one bit per pixel, compressed with zlib row by row so a large code never
 * holds its pixels in memory. A logo — PNG, JPEG or WebP — is drawn over the zone the code keeps for it with GD, and
 * the image is then written in 8-bit RGB.
 */
final readonly class PngWriter
{
    private const string COLOUR = '/^#[0-9a-fA-F]{6}$/D';

    /** The eight bytes every PNG file starts with. */
    private const string SIGNATURE = "\x89PNG\r\n\x1A\n";

    /**
     * @param int    $scale     the pixels on a side of a module, from 1 to 32
     * @param int    $quietZone the light modules around the code; the standard asks for 4
     * @param string $dark      the colour of the dark modules, `#rrggbb`
     * @param string $light     the colour of the light modules, `#rrggbb`
     *
     * @throws InvalidOption when an option is out of its range
     */
    public function __construct(
        private int $scale = 8,
        private int $quietZone = 4,
        private string $dark = '#000000',
        private string $light = '#ffffff',
    ) {
        if ($scale < 1 || $scale > 32) {
            throw InvalidOption::of('scale', 'from 1 to 32 pixels a module', (string) $scale);
        }

        if ($quietZone < 0 || $quietZone > 16) {
            throw InvalidOption::of('quiet zone', 'from 0 to 16 modules', (string) $quietZone);
        }

        foreach (['dark colour' => $dark, 'light colour' => $light] as $name => $colour) {
            if (1 !== preg_match(self::COLOUR, $colour)) {
                throw InvalidOption::of($name, 'a colour written #rrggbb', '"'.$colour.'"');
            }
        }
    }

    /**
     * The bytes of the PNG file.
     *
     * @throws InvalidOption when a logo is given for a code that keeps no zone for it, when it is an SVG, when GD is
     *                       missing, or when GD cannot read it
     */
    public function write(QrCode $code, ?Logo $logo = null): string
    {
        $side = ($code->size() + 2 * $this->quietZone) * $this->scale;
        $canvas = $logo instanceof Logo ? $this->canvas($logo, $code->zone) : null;
        $header = pack('NNCCCCC', $side, $side, null === $canvas ? 1 : 8, null === $canvas ? 3 : 2, 0, 0, 0);
        $png = self::SIGNATURE.$this->chunk('IHDR', $header);

        if (null === $canvas) {
            $png .= $this->chunk('PLTE', $this->rgb($this->light).$this->rgb($this->dark));
        }

        $deflate = deflate_init(\ZLIB_ENCODING_DEFLATE);

        if (!$deflate instanceof DeflateContext) {
            throw InvalidOption::of('compression of a PNG', 'available', 'no zlib context');
        }

        $data = '';

        foreach ($this->modules($code) as $y => $row) {
            $lines = null === $canvas ? str_repeat("\0".$this->packed($row), $this->scale) : implode('', array_map(static fn (string $line): string => "\0".$line, $this->truecolour($row, $y, ...$canvas)));
            $data .= deflate_add($deflate, $lines, \ZLIB_NO_FLUSH);
        }

        $data .= deflate_add($deflate, '', \ZLIB_FINISH);

        return $png.$this->chunk('IDAT', $data).$this->chunk('IEND', '');
    }

    /**
     * Each row of modules with its quiet zone, `1` for dark, the zone of a logo light.
     *
     * @return list<string>
     */
    private function modules(QrCode $code): array
    {
        $blank = str_repeat('0', $code->size() + 2 * $this->quietZone);
        $margin = str_repeat('0', $this->quietZone);
        $rows = [];
        $zone = $code->zone;

        for ($i = 0; $i < $this->quietZone; ++$i) {
            $rows[] = $blank;
        }

        foreach ($code->rows() as $y => $row) {
            if ($zone instanceof Zone && $y >= $zone->y && $y < $zone->y + $zone->side) {
                $row = substr_replace($row, str_repeat('0', $zone->side), $zone->x, $zone->side);
            }

            $rows[] = $margin.$row.$margin;
        }

        for ($i = 0; $i < $this->quietZone; ++$i) {
            $rows[] = $blank;
        }

        return $rows;
    }

    /**
     * A row of pixels at one bit each, the palette's index 1 for dark, padded to a byte.
     */
    private function packed(string $row): string
    {
        $bits = '';

        foreach (str_split($row) as $module) {
            $bits .= str_repeat($module, $this->scale);
        }

        $bits .= str_repeat('0', (8 - \strlen($bits) % 8) % 8);

        return implode('', array_map(static fn (string $byte): string => \chr((int) bindec($byte) & 0xFF), str_split($bits, 8)));
    }

    /**
     * The `$scale` rows of RGB pixels of a row of modules, the logo's pixels where the row crosses it.
     *
     * @return list<string>
     */
    private function truecolour(string $row, int $y, GdImage $canvas, Zone $zone): array
    {
        $dark = $this->rgb($this->dark);
        $light = $this->rgb($this->light);
        $line = '';

        foreach (str_split($row) as $module) {
            $line .= str_repeat('1' === $module ? $dark : $light, $this->scale);
        }

        $lines = [];
        $top = ($zone->y + $this->quietZone) * $this->scale;
        $left = ($zone->x + $this->quietZone) * $this->scale;
        $width = imagesx($canvas);

        for ($repeat = 0; $repeat < $this->scale; ++$repeat) {
            $py = $y * $this->scale + $repeat - $top;

            if ($py < 0 || $py >= $width) {
                $lines[] = $line;

                continue;
            }

            $pixels = '';

            for ($px = 0; $px < $width; ++$px) {
                $pixels .= substr(pack('N', imagecolorat($canvas, $px, $py)), 1);
            }

            $lines[] = substr_replace($line, $pixels, 3 * $left, 3 * $width);
        }

        return $lines;
    }

    /**
     * The zone of the logo in pixels, light, with the logo fitted in it, centred, its transparency blended; and the
     * zone.
     *
     * @return array{GdImage, Zone}
     */
    private function canvas(Logo $logo, ?Zone $zone): array
    {
        if (!$zone instanceof Zone) {
            throw InvalidOption::of('code of a logo', 'encoded with a zone for it', 'a code without one');
        }

        if ('image/svg+xml' === $logo->mediaType) {
            throw InvalidOption::of('logo of a PNG', 'a PNG, JPEG or WebP image', 'an SVG; draw it with SvgWriter');
        }

        if (!\function_exists('imagecreatefromstring')) {
            throw InvalidOption::of('logo of a PNG', 'drawn with GD', 'no GD extension');
        }

        $image = @imagecreatefromstring($logo->bytes);

        if (!$image instanceof GdImage) {
            throw InvalidOption::of('logo of a PNG', 'an image GD can read', 'unreadable bytes');
        }

        $side = $zone->side * $this->scale;
        $canvas = imagecreatetruecolor(max(1, $side), max(1, $side));

        if (!$canvas instanceof GdImage) {
            throw InvalidOption::of('logo of a PNG', 'drawn in memory', 'no image');
        }

        $light = $this->rgb($this->light);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, \ord($light[0]), \ord($light[1]), \ord($light[2])));
        // The logo's longer side spans the zone less half a module on each side; its proportions are kept.
        $box = $side - $this->scale;
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);
        $w = intdiv($width * $box, $longest);
        $h = intdiv($height * $box, $longest);
        imagecopyresampled($canvas, $image, intdiv($side - $w, 2), intdiv($side - $h, 2), 0, 0, $w, $h, $width, $height);

        return [$canvas, $zone];
    }

    private function rgb(string $colour): string
    {
        return pack('H*', substr($colour, 1));
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
