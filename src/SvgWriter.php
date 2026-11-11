<?php

declare(strict_types=1);

namespace Phil\QrCode;

use Phil\QrCode\Exception\InvalidOption;

/**
 * A QR code as a standalone SVG image: a light square with the quiet zone around the code, and every dark run of a
 * row in a single path, one unit per module, drawn with crisp edges so it scales to any size without blur. The zone
 * of a logo is left light, and the logo drawn over it. The image carries `role="img"` and a `<title>` that screen
 * readers announce.
 */
final readonly class SvgWriter
{
    private const string COLOUR = '/^#[0-9a-fA-F]{6}$/D';

    /**
     * @param int    $quietZone the light modules around the code; the standard asks for 4
     * @param string $dark      the colour of the dark modules, `#rrggbb`
     * @param string $light     the colour of the light modules, `#rrggbb`
     * @param int    $size      the width and height written on the image, in pixels; 0 writes none, and the image
     *                          takes the size its container gives it
     *
     * @throws InvalidOption when the quiet zone is out of 0 to 16, a colour is not `#rrggbb`, or the size is out of
     *                       0 to 4096
     */
    public function __construct(
        private int $quietZone = 4,
        private string $dark = '#000000',
        private string $light = '#ffffff',
        private int $size = 0,
    ) {
        if ($quietZone < 0 || $quietZone > 16) {
            throw InvalidOption::of('quiet zone', 'from 0 to 16 modules', (string) $quietZone);
        }

        foreach (['dark colour' => $dark, 'light colour' => $light] as $name => $colour) {
            if (1 !== preg_match(self::COLOUR, $colour)) {
                throw InvalidOption::of($name, 'a colour written #rrggbb', '"'.$colour.'"');
            }
        }

        if ($size < 0 || $size > 4096) {
            throw InvalidOption::of('size', 'from 0 to 4096 pixels', (string) $size);
        }
    }

    /**
     * The SVG of the code; `$title` names it for screen readers, the code's own text when empty.
     *
     * @throws InvalidOption when a logo is given for a code that keeps no zone for it
     */
    public function write(QrCode $code, string $title = '', ?Logo $logo = null): string
    {
        $zone = $code->zone;

        if ($logo instanceof Logo && !$zone instanceof Zone) {
            throw InvalidOption::of('code of a logo', 'encoded with a zone for it', 'a code without one');
        }

        $q = $this->quietZone;
        $side = $code->size() + 2 * $q;
        $path = '';

        foreach ($code->rows() as $y => $row) {
            if ($zone instanceof Zone && $y >= $zone->y && $y < $zone->y + $zone->side) {
                $row = substr_replace($row, str_repeat('0', $zone->side), $zone->x, $zone->side);
            }

            preg_match_all('/1+/', $row, $runs, \PREG_OFFSET_CAPTURE);

            foreach ($runs[0] as [$run, $x]) {
                $path .= \sprintf('M%d %dh%dv1h-%dz', $x + $q, $y + $q, \strlen($run), \strlen($run));
            }
        }

        $image = $logo instanceof Logo ? \sprintf(
            '<image x="%1$d.5" y="%2$d.5" width="%3$d" height="%3$d" preserveAspectRatio="xMidYMid meet" href="%4$s"/>',
            $zone->x + $q,
            $zone->y + $q,
            $zone->side - 1,
            $logo->dataUri(),
        ) : '';

        return \sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d"%2$s role="img" shape-rendering="crispEdges"><title>%3$s</title><rect width="%1$d" height="%1$d" fill="%4$s"/><path fill="%5$s" d="%6$s"/>%7$s</svg>',
            $side,
            0 === $this->size ? '' : \sprintf(' width="%1$d" height="%1$d"', $this->size),
            htmlspecialchars('' === $title ? $code->data : $title, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_XML1, 'UTF-8'),
            $this->light,
            $this->dark,
            $path,
            $image,
        );
    }
}
