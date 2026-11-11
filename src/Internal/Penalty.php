<?php

declare(strict_types=1);

namespace Phil\QrCode\Internal;

/**
 * The penalty of a masked symbol, by the four rules of ISO/IEC 18004: runs of five or more modules of one colour,
 * 2 × 2 squares of one colour, patterns that look like a finder, and a share of dark modules far from half. The mask
 * of the lowest penalty is the easiest to read.
 *
 * @internal
 */
final class Penalty
{
    /** A finder's 1:1:3:1:1 core with four light modules after it, and the same read backwards. */
    private const array FINDER_LIKE = ['10111010000', '00001011101'];

    /**
     * @param array<int, int> $modules
     */
    public static function of(array $modules, int $size): int
    {
        $rows = [];
        $columns = array_fill(0, $size, '');

        for ($y = 0; $y < $size; ++$y) {
            $row = implode('', \array_slice($modules, $y * $size, $size));
            $rows[] = $row;

            for ($x = 0; $x < $size; ++$x) {
                $columns[$x] .= $row[$x];
            }
        }

        $penalty = 0;

        foreach ([...$rows, ...$columns] as $line) {
            $penalty += self::runs($line) + 40 * self::finders($line);
        }

        return $penalty + self::squares($rows, $size) + self::balance($modules);
    }

    /**
     * 3 for a run of five modules of one colour, and 1 for each module more.
     */
    public static function runs(string $line): int
    {
        $penalty = 0;
        preg_match_all('/0{5,}|1{5,}/', $line, $matches);

        foreach ($matches[0] as $run) {
            $penalty += \strlen($run) - 2;
        }

        return $penalty;
    }

    public static function finders(string $line): int
    {
        $count = 0;

        foreach (self::FINDER_LIKE as $pattern) {
            for ($at = strpos($line, $pattern); false !== $at; $at = strpos($line, $pattern, $at + 1)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * 3 for each 2 × 2 square of one colour, overlapping ones counted each.
     *
     * @param list<string> $rows
     */
    public static function squares(array $rows, int $size): int
    {
        $penalty = 0;

        for ($y = 1; $y < $size; ++$y) {
            for ($x = 1; $x < $size; ++$x) {
                $colour = $rows[$y][$x];

                if ($colour === $rows[$y][$x - 1] && $colour === $rows[$y - 1][$x] && $colour === $rows[$y - 1][$x - 1]) {
                    $penalty += 3;
                }
            }
        }

        return $penalty;
    }

    /**
     * 10 for each step of 5 % the share of dark modules strays from half, a share from 45 % to 55 % costing nothing.
     *
     * @param array<int, int> $modules
     */
    public static function balance(array $modules): int
    {
        $dark = array_sum($modules);

        return 10 * max(0, (int) ceil(abs(20 * $dark - 10 * \count($modules)) / \count($modules)) - 1);
    }
}
