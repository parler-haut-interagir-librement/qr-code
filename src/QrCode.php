<?php

declare(strict_types=1);

namespace Phil\QrCode;

use Closure;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\DataTooLong;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Internal\Encoder;
use Phil\QrCode\Internal\Penalty;
use Phil\QrCode\Internal\Segmenter;
use Phil\QrCode\Internal\Symbol;

/**
 * A QR code (model 2, ISO/IEC 18004): the text in the shortest sequence of numeric, alphanumeric, byte and kanji
 * segments, behind the ECI header of UTF-8 when its bytes need it, in the smallest version from 1 to 40 that holds it
 * at the level of error correction, under the mask of the lowest penalty.
 *
 * The code is a square of modules, read by `isDark()` or `rows()`; `SvgWriter` and `PngWriter` draw it. A code made
 * for a logo keeps a zone at its centre, which the writers leave light for the logo.
 */
final readonly class QrCode
{
    /** The largest share of the side a logo may take. */
    public const float MAX_LOGO_ZONE = 0.3;

    /**
     * @param list<Segment> $segments the segments written, in order
     * @param list<string>  $rows     each row of modules, `1` for dark and `0` for light
     */
    private function __construct(
        public string $data,
        public ErrorCorrection $level,
        public array $segments,
        public int $version,
        public int $mask,
        public ?Zone $zone,
        private array $rows,
    ) {
    }

    /**
     * A text, or the payload of a typed content, as a QR code.
     *
     * @param int      $minVersion the smallest version to use, 1 to 40, for codes printed at one size
     * @param int|null $mask       the mask, 0 to 7; the one of the lowest penalty when null
     * @param float    $logoZone   the share of the side kept at the centre for a logo, 0 to 0.3; above 0, the level is
     *                             raised to High, and the version is the smallest whose blocks can correct the zone
     * @param bool     $eci        whether bytes beyond ASCII are announced as UTF-8 by an ECI header
     * @param bool     $kanji      whether the double-byte characters of Shift JIS may be written in the kanji mode
     *
     * @throws DataTooLong   when the text does not fit in version 40 at that level
     * @throws InvalidOption when the smallest version, the mask or the zone is out of range
     */
    public static function encode(
        string|Content $data,
        ErrorCorrection $level = ErrorCorrection::Medium,
        int $minVersion = 1,
        ?int $mask = null,
        float $logoZone = 0.0,
        bool $eci = true,
        bool $kanji = true,
    ): self {
        $text = $data instanceof Content ? $data->payload() : $data;

        // Version 40 holds at most 2953 bytes, 4296 alphanumeric characters or 7089 digits: longer is refused
        // before any bit is computed.
        if (\strlen($text) > 7089) {
            throw DataTooLong::at(\strlen($text) * 3, $level);
        }

        $ranges = [];

        return self::build($text, static function (int $version) use ($text, $eci, $kanji, &$ranges): array {
            // The versions whose count fields have the same widths share their segments.
            return $ranges[Mode::Numeric->countBits($version)] ??= Segmenter::split($text, $version, $eci, $kanji);
        }, $level, $minVersion, $mask, $logoZone);
    }

    /**
     * Segments built by the caller as a QR code, in their order.
     *
     * @param non-empty-list<Segment> $segments
     *
     * @throws DataTooLong   when the segments do not fit in version 40 at that level
     * @throws InvalidOption when the smallest version, the mask or the zone is out of range
     */
    public static function fromSegments(array $segments, ErrorCorrection $level = ErrorCorrection::Medium, int $minVersion = 1, ?int $mask = null, float $logoZone = 0.0): self
    {
        $text = implode('', array_map(static fn (Segment $segment): string => $segment->text, $segments));
        $bits = array_sum(array_map(static fn (Segment $segment): int => \strlen($segment->bits), $segments));

        // Version 40 holds at most 2956 data codewords: more bits than those are refused before any version is tried.
        if ($bits > 2956 * 8) {
            throw DataTooLong::at($bits, $level);
        }

        return self::build($text, static fn (): array => $segments, $level, $minVersion, $mask, $logoZone);
    }

    /**
     * The modules on a side, 21 for version 1 to 177 for version 40.
     */
    public function size(): int
    {
        return \count($this->rows);
    }

    /**
     * Whether the module of a column and a row, from the top left corner, is dark.
     */
    public function isDark(int $x, int $y): bool
    {
        return '1' === ($this->rows[$y][$x] ?? '0');
    }

    /**
     * Each row of modules, `1` for dark and `0` for light; the zone of a logo is not cleared.
     *
     * @return list<string>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @param Closure(int<1, 40>): list<Segment> $segments
     */
    private static function build(string $text, Closure $segments, ErrorCorrection $level, int $minVersion, ?int $mask, float $logoZone): self
    {
        if ($minVersion < 1 || $minVersion > 40) {
            throw InvalidOption::of('smallest version', 'from 1 to 40', (string) $minVersion);
        }

        if (null !== $mask && ($mask < 0 || $mask > 7)) {
            throw InvalidOption::of('mask', 'from 0 to 7', (string) $mask);
        }

        if ($logoZone < 0 || $logoZone > self::MAX_LOGO_ZONE) {
            throw InvalidOption::of('zone of the logo', 'from 0 to 0.3 of the side', (string) $logoZone);
        }

        if ($logoZone > 0) {
            $level = ErrorCorrection::High;
        }

        [$version, $bits, $written, $zone] = Encoder::fit($segments, $level, $minVersion, $logoZone);
        $symbol = new Symbol($version);
        $symbol->place(Encoder::codewords($bits, $version, $level));
        $mask ??= self::bestMask($symbol, $level);
        $symbol->mask($mask);
        $symbol->drawFormat($level, $mask);

        $size = $symbol->size();

        return new self($text, $level, $written, $version, $mask, $zone, array_map(
            static fn (array $row): string => implode('', $row),
            array_chunk($symbol->modules, $size),
        ));
    }

    private static function bestMask(Symbol $symbol, ErrorCorrection $level): int
    {
        $best = 0;
        $lowest = \PHP_INT_MAX;

        for ($mask = 0; $mask < 8; ++$mask) {
            $symbol->mask($mask);
            $symbol->drawFormat($level, $mask);
            $penalty = Penalty::of($symbol->modules, $symbol->size());
            $symbol->mask($mask);

            if ($penalty < $lowest) {
                $best = $mask;
                $lowest = $penalty;
            }
        }

        return $best;
    }
}
