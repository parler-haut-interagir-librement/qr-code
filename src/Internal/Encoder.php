<?php

declare(strict_types=1);

namespace Phil\QrCode\Internal;

use Closure;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\DataTooLong;
use Phil\QrCode\Segment;
use Phil\QrCode\Zone;

/**
 * The codewords of a text: its segments in the smallest version that holds them — and that leaves the zone of a logo
 * readable —, ended and padded, split into blocks, each followed by its error correction, interleaved as the symbol
 * reads them.
 *
 * Bits are kept as a string of `0` and `1`.
 *
 * @internal
 */
final class Encoder
{
    /** The pad codewords that fill the data codewords left, in turn. */
    private const array PADS = [0xEC, 0x11];

    /**
     * The smallest version from `$minVersion` that holds the segments at the level and, with a logo, whose zone each
     * block can correct; with the bits, the segments and the zone.
     *
     * @param Closure(int<1, 40>): list<Segment> $segments   the segments for a version, the same within a range of versions
     * @param int<1, 40>                         $minVersion
     * @param float                              $logoZone   the share of the side kept for a logo, 0 for none
     *
     * @return array{int<1, 40>, string, list<Segment>, ?Zone}
     *
     * @throws DataTooLong
     */
    public static function fit(Closure $segments, ErrorCorrection $level, int $minVersion, float $logoZone = 0.0): array
    {
        $bits = '';

        for ($version = $minVersion; $version <= 40; ++$version) {
            $written = $segments($version);

            $bits = implode('', array_map(static fn (Segment $segment): string => $segment->encode($version), $written));

            if (\strlen($bits) > Capacity::dataCodewords($version, $level) * 8) {
                continue;
            }

            $zone = $logoZone > 0 ? self::zone($version, $level, $logoZone) : null;

            if ($logoZone <= 0 || $zone instanceof Zone) {
                return [$version, $bits, $written, $zone];
            }
        }

        throw DataTooLong::at(\strlen($bits), $level);
    }

    /**
     * The codewords of the symbol, in the order they are placed: the bits ended by up to four zeros, padded to a
     * byte, then filled with the pad codewords, split into blocks, then the n-th data codeword of every block in
     * turn, and the error correction codewords likewise.
     *
     * @return list<int>
     */
    public static function codewords(string $bits, int $version, ErrorCorrection $level): array
    {
        $capacity = Capacity::dataCodewords($version, $level) * 8;
        $bits .= str_repeat('0', min(4, $capacity - \strlen($bits)));
        $bits .= str_repeat('0', (8 - \strlen($bits) % 8) % 8);
        $data = array_map(static fn (string $byte): int => \intval($byte, 2), str_split($bits, 8));

        for ($i = 0; \count($data) < $capacity / 8; ++$i) {
            $data[] = self::PADS[$i % 2];
        }

        $blocks = self::split($data, $version, $level);
        $degree = Capacity::correctionPerBlock($version, $level);
        $corrections = array_map(static fn (array $block): array => ReedSolomon::remainder($block, $degree), $blocks);

        return [...self::interleave($blocks), ...self::interleave($corrections)];
    }

    /**
     * The block of each codeword of the symbol, in the order they are placed.
     *
     * @param int<1, 40> $version
     *
     * @return list<int>
     */
    public static function owners(int $version, ErrorCorrection $level): array
    {
        $count = Capacity::blocks($version, $level);
        $degree = Capacity::correctionPerBlock($version, $level);
        $blocks = self::split(range(1, Capacity::dataCodewords($version, $level)), $version, $level);
        $byBlock = [];

        foreach ($blocks as $b => $block) {
            $byBlock[] = array_map(static fn (int $codeword): int => $b, $block);
        }

        $owners = self::interleave($byBlock);

        for ($i = 0; $i < $degree; ++$i) {
            array_push($owners, ...range(0, $count - 1));
        }

        return $owners;
    }

    /**
     * The centred zone of a logo at a version, when it stays clear of the finders, the timing patterns and the format
     * and version information, and when no block loses more than a quarter of its error correction codewords — half
     * of what it can correct, the rest left for wear; null otherwise.
     *
     * @param int<1, 40> $version
     */
    public static function zone(int $version, ErrorCorrection $level, float $share): ?Zone
    {
        $size = 17 + 4 * $version;
        // An odd side, so the zone sits on the centre module.
        $side = ((int) ceil($share * $size)) | 1;
        $start = intdiv($size - $side, 2);

        if ($start < 9) {
            return null; // too close to the finders
        }

        $zone = new Zone($start, $start, $side);
        $owners = self::owners($version, $level);
        $touched = [];

        foreach (new Symbol($version)->positions() as $bit => $index) {
            $codeword = $bit >> 3;

            if (isset($owners[$codeword]) && $zone->contains($index % $size, intdiv($index, $size))) {
                $touched[$codeword] = $owners[$codeword];
            }
        }

        $budget = intdiv(Capacity::correctionPerBlock($version, $level), 4);

        return array_all(array_count_values($touched), static fn (int $lost): bool => $lost <= $budget) ? $zone : null;
    }

    /**
     * Items split into the blocks of a version, the short blocks first, the long ones one item longer.
     *
     * @template T
     *
     * @param list<T> $items
     *
     * @return non-empty-list<list<T>>
     */
    private static function split(array $items, int $version, ErrorCorrection $level): array
    {
        $blocks = Capacity::blocks($version, $level);
        $short = intdiv(\count($items), $blocks);
        $longs = \count($items) % $blocks;
        $split = [];
        $offset = 0;

        for ($b = 0; $b < $blocks; ++$b) {
            $length = $short + ($b >= $blocks - $longs ? 1 : 0);
            $split[] = \array_slice($items, $offset, $length);
            $offset += $length;
        }

        /** @var non-empty-list<list<T>> $split a version has at least one block */
        return $split;
    }

    /**
     * The n-th item of every list in turn, the lists that run out skipped; no list is longer than the last.
     *
     * @template T
     *
     * @param non-empty-list<list<T>> $lists
     *
     * @return list<T>
     */
    private static function interleave(array $lists): array
    {
        $result = [];

        // The last list is the longest: the long blocks come after the short ones.
        foreach (array_keys($lists[\count($lists) - 1]) as $i) {
            foreach ($lists as $list) {
                if (\array_key_exists($i, $list)) {
                    $result[] = $list[$i];
                }
            }
        }

        return $result;
    }
}
