<?php

declare(strict_types=1);

namespace Phil\QrCode\Internal;

use Phil\QrCode\ErrorCorrection;

/**
 * What a version holds: its modules left for data, its codewords, and how they split into blocks of error correction
 * at each level, from the tables of ISO/IEC 18004.
 *
 * @internal
 */
final class Capacity
{
    /** The error correction codewords of each block, by level, for versions 1 to 40. */
    private const array CODEWORDS_PER_BLOCK = [
        'L' => [7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'M' => [10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        'Q' => [13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'H' => [17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** The blocks of error correction, by level, for versions 1 to 40. */
    private const array BLOCKS = [
        'L' => [1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        'M' => [1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        'Q' => [1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        'H' => [1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    /**
     * The modules of a version left for codewords and remainder bits: the whole square, less the finder patterns
     * with their separators and format areas, the timing patterns, the alignment patterns and, from version 7, the
     * version information.
     */
    public static function dataModules(int $version): int
    {
        $size = 17 + 4 * $version;
        $alignments = \count(self::alignments($version));
        $patterns = 0 === $alignments ? 0 : $alignments * $alignments - 3;
        // Each finder takes 8 × 8 with its separator, the format information 31 modules and the dark module 1; the
        // timing rows run between the separators, less the alignment patterns they cross.
        $taken = 3 * 64 + 31 + 2 * ($size - 16) + 25 * $patterns - 2 * 5 * max(0, $alignments - 2);

        return $size * $size - $taken - ($version >= 7 ? 36 : 0);
    }

    /**
     * Every codeword of a version, data and error correction.
     */
    public static function codewords(int $version): int
    {
        return intdiv(self::dataModules($version), 8);
    }

    public static function blocks(int $version, ErrorCorrection $level): int
    {
        return self::BLOCKS[$level->value][$version - 1];
    }

    public static function correctionPerBlock(int $version, ErrorCorrection $level): int
    {
        return self::CODEWORDS_PER_BLOCK[$level->value][$version - 1];
    }

    /**
     * The data codewords of a version at a level.
     */
    public static function dataCodewords(int $version, ErrorCorrection $level): int
    {
        return self::codewords($version) - self::blocks($version, $level) * self::correctionPerBlock($version, $level);
    }

    /**
     * The rows (and columns) of the centres of the alignment patterns: none for version 1; else 6, the last row less
     * 6, and those between at an even step that the standard sets to 26 for version 32.
     *
     * @return list<int>
     */
    public static function alignments(int $version): array
    {
        if (1 === $version) {
            return [];
        }

        $count = intdiv($version, 7) + 2;
        $last = 4 * $version + 10;
        $step = 32 === $version ? 26 : (int) ceil(($last - 6) / ($count - 1) / 2) * 2;
        $centres = [6];

        for ($i = $count - 2; $i >= 0; --$i) {
            $centres[] = $last - $i * $step;
        }

        return $centres;
    }
}
