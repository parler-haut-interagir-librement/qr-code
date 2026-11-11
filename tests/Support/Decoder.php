<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Support;

use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Internal\Capacity;
use RuntimeException;

/**
 * A QR code reader for the tests, written apart from the encoder: it reads the format information, marks the function
 * patterns from the standard's table of alignment centres, reads the modules up and down the strips, removes the mask,
 * splits the codewords back into their blocks, checks every block by its syndromes — or solves the codewords it is
 * told are lost, from those syndromes —, and parses the segments.
 *
 * Only the block table of the standard is shared with the encoder, through `Capacity`.
 */
final class Decoder
{
    /** The rows and columns of the alignment centres, by version, from ISO/IEC 18004, annex E. */
    private const array ALIGNMENTS = [
        [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50],
        [6, 30, 54], [6, 32, 58], [6, 34, 62], [6, 26, 46, 66], [6, 26, 48, 70], [6, 26, 50, 74], [6, 30, 54, 78],
        [6, 30, 56, 82], [6, 30, 58, 86], [6, 34, 62, 90], [6, 28, 50, 72, 94], [6, 26, 50, 74, 98],
        [6, 30, 54, 78, 102], [6, 28, 54, 80, 106], [6, 32, 58, 84, 110], [6, 30, 58, 86, 114], [6, 34, 62, 90, 118],
        [6, 26, 50, 74, 98, 122], [6, 30, 54, 78, 102, 126], [6, 26, 52, 78, 104, 130], [6, 30, 56, 82, 108, 134],
        [6, 34, 60, 86, 112, 138], [6, 30, 58, 86, 114, 142], [6, 34, 62, 90, 118, 146], [6, 30, 54, 78, 102, 126, 150],
        [6, 24, 50, 76, 102, 128, 154], [6, 28, 54, 80, 106, 132, 158], [6, 32, 58, 84, 110, 136, 162],
        [6, 26, 54, 82, 110, 138, 166], [6, 30, 58, 86, 114, 142, 170],
    ];

    /**
     * The segments of a symbol, each a mode and its text — UTF-8, bytes as they are, kanji converted from Shift JIS —
     * and the ECI assignments as `eci:<n>`.
     *
     * @param list<string>   $rows the rows of modules, `1` for dark
     * @param list<int>|null $lost the indices of the modules to take as unreadable — under a logo
     *
     * @return list<array{string, string}>
     */
    public static function segments(array $rows, ?array $lost = null): array
    {
        $size = \count($rows);
        $version = intdiv($size - 17, 4);
        [$level, $mask] = self::format($rows);
        $function = self::functionModules($version, $size);
        $bits = [];
        $erased = [];

        foreach (self::zigzag($size, $function) as $n => [$x, $y]) {
            $dark = '1' === $rows[$y][$x];
            $bits[] = $dark !== self::masked($mask, $x, $y) ? 1 : 0;

            if (null !== $lost && \in_array($y * $size + $x, $lost, true)) {
                $erased[$n >> 3] = true;
            }
        }

        $total = intdiv(\count($bits), 8);
        $codewords = [];

        for ($i = 0; $i < $total; ++$i) {
            $byte = 0;

            for ($b = 0; $b < 8; ++$b) {
                $byte = $byte << 1 | $bits[8 * $i + $b];
            }

            $codewords[] = $byte;
        }

        return SegmentParser::parse(self::data($codewords, $version, $level, array_keys($erased)), $version);
    }

    /**
     * The text of a symbol: its segments' texts joined.
     *
     * @param list<string> $rows
     */
    public static function text(array $rows): string
    {
        return implode('', array_map(static fn (array $segment): string => str_starts_with($segment[0], 'eci') ? '' : $segment[1], self::segments($rows)));
    }

    /**
     * For each codeword in placement order, its block — the de-interleaving of the reader.
     *
     * @return list<int>
     */
    public static function owners(int $version, string $level): array
    {
        $owners = [];

        foreach (self::layout($version, $level) as $index => [$block]) {
            $owners[$index] = $block;
        }

        ksort($owners);

        return array_values($owners);
    }

    /**
     * @param list<string> $rows
     *
     * @return array{string, int}
     */
    private static function format(array $rows): array
    {
        $read = 0;

        for ($i = 0; $i < 15; ++$i) {
            [$x, $y] = match (true) {
                $i <= 5 => [8, $i],
                6 === $i => [8, 7],
                7 === $i => [8, 8],
                8 === $i => [7, 8],
                default => [14 - $i, 8],
            };
            $read |= ('1' === $rows[$y][$x] ? 1 : 0) << $i;
        }

        $best = null;
        $distance = 16;

        for ($value = 0; $value < 32; ++$value) {
            $remainder = $value << 10;

            for ($bit = 14; $bit >= 10; --$bit) {
                if (0 !== ($remainder >> $bit & 1)) {
                    $remainder ^= 0b10100110111 << ($bit - 10);
                }
            }

            $candidate = ($value << 10 | $remainder) ^ 0b101010000010010;
            $d = substr_count(decbin($candidate ^ $read), '1');

            if ($d < $distance) {
                $distance = $d;
                $best = $value;
            }
        }

        if (null === $best || $distance > 3) {
            throw new RuntimeException('The format information cannot be read.');
        }

        return [['M', 'L', 'H', 'Q'][$best >> 3], $best & 7];
    }

    /**
     * @return array<int, true>
     */
    private static function functionModules(int $version, int $size): array
    {
        // The finders with their separators and the format areas, then the timing patterns.
        $rectangles = [[0, 0, 9, 9], [$size - 8, 0, 8, 9], [0, $size - 8, 9, 8], [6, 0, 1, $size], [0, 6, $size, 1]];
        $centres = self::ALIGNMENTS[$version - 1];

        foreach ($centres as $cx) {
            foreach ($centres as $cy) {
                if (!(($cx < 9 && $cy < 9) || ($cx < 9 && $cy > $size - 10) || ($cx > $size - 10 && $cy < 9))) {
                    $rectangles[] = [$cx - 2, $cy - 2, 5, 5];
                }
            }
        }

        if ($version >= 7) {
            $rectangles[] = [0, $size - 11, 6, 3];
            $rectangles[] = [$size - 11, 0, 3, 6];
        }

        $marked = [];

        foreach ($rectangles as [$left, $top, $width, $height]) {
            for ($y = $top; $y < $top + $height; ++$y) {
                for ($x = $left; $x < $left + $width; ++$x) {
                    $marked[$y * $size + $x] = true;
                }
            }
        }

        return $marked;
    }

    /**
     * @param array<int, true> $function
     *
     * @return list<array{int, int}>
     */
    private static function zigzag(int $size, array $function): array
    {
        $order = [];
        $upward = true;

        for ($right = $size - 1; $right > 0; $right -= 2) {
            if (6 === $right) {
                --$right;
            }

            for ($i = 0; $i < $size; ++$i) {
                $y = $upward ? $size - 1 - $i : $i;

                foreach ([$right, $right - 1] as $x) {
                    if (!isset($function[$y * $size + $x])) {
                        $order[] = [$x, $y];
                    }
                }
            }

            $upward = !$upward;
        }

        return $order;
    }

    private static function masked(int $mask, int $x, int $y): bool
    {
        // The conditions of ISO/IEC 18004 written with i the row and j the column.
        [$i, $j] = [$y, $x];

        return match ($mask) {
            0 => 0 === ($i + $j) % 2,
            1 => 0 === $i % 2,
            2 => 0 === $j % 3,
            3 => 0 === ($i + $j) % 3,
            4 => 0 === (intdiv($i, 2) + intdiv($j, 3)) % 2,
            5 => 0 === ($i * $j) % 2 + ($i * $j) % 3,
            6 => 0 === (($i * $j) % 2 + ($i * $j) % 3) % 2,
            default => 0 === (($i + $j) % 2 + ($i * $j) % 3) % 2,
        };
    }

    /**
     * Where each codeword of the symbol belongs: its block and its place in the block.
     *
     * @return list<array{int, int}>
     */
    private static function layout(int $version, string $level): array
    {
        $blocks = Capacity::blocks($version, ErrorCorrection::from($level));
        $ec = Capacity::correctionPerBlock($version, ErrorCorrection::from($level));
        $total = Capacity::codewords($version);
        $dataTotal = $total - $blocks * $ec;
        $shortData = intdiv($dataTotal, $blocks);
        $longCount = $dataTotal % $blocks;
        $layout = [];

        for ($i = 0; $i <= $shortData; ++$i) {
            for ($b = 0; $b < $blocks; ++$b) {
                $length = $shortData + ($b >= $blocks - $longCount ? 1 : 0);

                if ($i < $length) {
                    $layout[] = [$b, $i];
                }
            }
        }

        for ($i = 0; $i < $ec; ++$i) {
            for ($b = 0; $b < $blocks; ++$b) {
                $length = $shortData + ($b >= $blocks - $longCount ? 1 : 0);
                $layout[] = [$b, $length + $i];
            }
        }

        return $layout;
    }

    /**
     * The data codewords, every block checked — and its lost codewords solved — by its syndromes.
     *
     * @param list<int> $codewords
     * @param list<int> $erased    the indices of the lost codewords, in placement order
     *
     * @return list<int>
     */
    private static function data(array $codewords, int $version, string $level, array $erased): array
    {
        $ec = Capacity::correctionPerBlock($version, ErrorCorrection::from($level));
        $blocks = [];
        $lost = [];

        foreach (self::layout($version, $level) as $index => [$block, $place]) {
            $blocks[$block][$place] = $codewords[$index];

            if (\in_array($index, $erased, true)) {
                $lost[$block][] = $place;
            }
        }

        $data = [];

        foreach ($blocks as $b => $block) {
            ksort($block);
            $block = array_values($block);
            $block = ReedSolomonCheck::solve($block, $ec, $lost[$b] ?? []);

            foreach (ReedSolomonCheck::syndromes($block, $ec) as $syndrome) {
                if (0 !== $syndrome) {
                    throw new RuntimeException('Block '.$b.' fails its Reed-Solomon check.');
                }
            }

            $data[$b] = \array_slice($block, 0, \count($block) - $ec);
        }

        ksort($data);

        return array_merge(...$data);
    }
}
