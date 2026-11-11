<?php

declare(strict_types=1);

namespace Phil\QrCode\Internal;

use Phil\QrCode\ErrorCorrection;

/**
 * The modules of a symbol: the function patterns drawn first and reserved, the codewords laid in two-column strips
 * from the bottom right corner, a mask over the data, then the format and version information.
 *
 * Modules are kept in one flat list, row by row, `1` for dark.
 *
 * @internal
 */
final class Symbol
{
    /** @var array<int, int> */
    public array $modules;

    /** @var array<int, bool> the modules of the function patterns and of the information areas */
    private array $reserved = [];

    /**
     * @param int<1, 40> $version
     */
    public function __construct(
        public readonly int $version,
    ) {
        $size = $this->size();
        $this->modules = array_fill(0, $size * $size, 0);
        $this->drawFunctionPatterns();
    }

    /**
     * @return int<21, 177>
     */
    public function size(): int
    {
        return 17 + 4 * $this->version;
    }

    public function isDark(int $x, int $y): bool
    {
        return 1 === $this->modules[$y * $this->size() + $x];
    }

    /**
     * The codewords laid in the free modules, bit by bit from the most significant, up and down the strips of two
     * columns from the right, the column of the vertical timing pattern skipped; the remainder modules stay light.
     *
     * @param list<int> $codewords
     */
    public function place(array $codewords): void
    {
        foreach (\array_slice($this->positions(), 0, \count($codewords) * 8) as $bit => $index) {
            $this->modules[$index] = ($codewords[$bit >> 3] >> (7 - ($bit & 7))) & 1;
        }
    }

    /**
     * The free modules in the order the bits are laid in them, by their index in the flat list: the n-th bit of the
     * codewords goes in the n-th module, codeword n >> 3.
     *
     * @return list<int>
     */
    public function positions(): array
    {
        $size = $this->size();
        $positions = [];

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if (6 === $right) {
                $right = 5;
            }

            $upward = 0 === (($size - 1 - $right) >> 1) % 2;

            for ($step = 0; $step < $size; ++$step) {
                $y = $upward ? $size - 1 - $step : $step;

                for ($dx = 0; $dx < 2; ++$dx) {
                    $index = $y * $size + $right - $dx;

                    if (!$this->isReserved($index)) {
                        $positions[] = $index;
                    }
                }
            }
        }

        return $positions;
    }

    /**
     * The data modules inverted where the mask's condition holds; applied twice, the mask is undone.
     */
    public function mask(int $mask): void
    {
        $size = $this->size();

        for ($y = 0; $y < $size; ++$y) {
            for ($x = 0; $x < $size; ++$x) {
                $index = $y * $size + $x;

                if (!$this->isReserved($index) && self::masked($mask, $x, $y)) {
                    $this->modules[$index] ^= 1;
                }
            }
        }
    }

    /**
     * The level and the mask, protected by a BCH code and drawn twice, around the top left finder and split between
     * the two others.
     */
    public function drawFormat(ErrorCorrection $level, int $mask): void
    {
        $bits = self::bch($level->formatBits() << 3 | $mask, 5, 0x537, 10) ^ 0x5412;

        foreach ($this->formatPositions() as $i => [$x, $y]) {
            $this->set($x, $y, ($bits >> ($i % 15)) & 1);
        }
    }

    /**
     * Where the fifteen bits of the format information go, twice: around the top left finder, down column 8 then
     * left along row 8, the timing patterns skipped; then right of row 8 for the low bits and bottom of column 8 for
     * the high ones.
     *
     * @return list<array{int, int}>
     */
    private function formatPositions(): array
    {
        $size = $this->size();
        $around = [];
        $split = [];

        for ($i = 0; $i < 15; ++$i) {
            $around[] = $i < 8 ? [8, $i < 6 ? $i : $i + 1] : [$i < 9 ? 7 : 14 - $i, 8];
            $split[] = $i < 8 ? [$size - 1 - $i, 8] : [8, $size - 15 + $i];
        }

        return [...$around, ...$split];
    }

    /**
     * Whether the condition of a mask holds at a module.
     */
    public static function masked(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => 0 === ($x + $y) % 2,
            1 => 0 === $y % 2,
            2 => 0 === $x % 3,
            3 => 0 === ($x + $y) % 3,
            4 => 0 === (intdiv($x, 3) + intdiv($y, 2)) % 2,
            5 => 0 === $x * $y % 2 + $x * $y % 3,
            6 => 0 === ($x * $y % 2 + $x * $y % 3) % 2,
            default => 0 === (($x + $y) % 2 + $x * $y % 3) % 2,
        };
    }

    /**
     * The value, of `$width` bits, followed by the remainder of its division by the generator, of `$degree` bits.
     */
    public static function bch(int $value, int $width, int $generator, int $degree): int
    {
        $remainder = $value << $degree;

        for ($bit = $degree + $width - 1; $bit >= $degree; --$bit) {
            if (1 === ($remainder >> $bit & 1)) {
                $remainder ^= $generator << ($bit - $degree);
            }
        }

        return $value << $degree | $remainder;
    }

    private function drawFunctionPatterns(): void
    {
        $size = $this->size();

        for ($i = 0; $i < $size; ++$i) {
            $this->set(6, $i, 0 === $i % 2 ? 1 : 0);
            $this->set($i, 6, 0 === $i % 2 ? 1 : 0);
        }

        $this->drawFinder(3, 3);
        $this->drawFinder($size - 4, 3);
        $this->drawFinder(3, $size - 4);

        $centres = Capacity::alignments($this->version);
        $last = \count($centres) - 1;

        foreach ($centres as $i => $cx) {
            foreach ($centres as $j => $cy) {
                if ((0 !== $i || 0 !== $j) && (0 !== $i || $last !== $j) && ($last !== $i || 0 !== $j)) {
                    $this->drawSquare($cx, $cy);
                }
            }
        }

        // Reserved until the mask is chosen, then drawn by drawFormat().
        foreach ($this->formatPositions() as [$x, $y]) {
            $this->reserved[$y * $size + $x] = true;
        }

        $this->set(8, $size - 8, 1);
        $this->drawVersion();
    }

    /**
     * A finder pattern — dark 7 × 7 ring, light ring, dark 3 × 3 core — with its light separator, clipped to the
     * symbol.
     */
    private function drawFinder(int $cx, int $cy): void
    {
        $size = $this->size();

        for ($y = max(0, $cy - 4); $y <= min($size - 1, $cy + 4); ++$y) {
            for ($x = max(0, $cx - 4); $x <= min($size - 1, $cx + 4); ++$x) {
                $distance = max(abs($x - $cx), abs($y - $cy));
                $this->set($x, $y, 2 !== $distance && 4 !== $distance ? 1 : 0);
            }
        }
    }

    /**
     * An alignment pattern: a 5 × 5 dark ring, a light ring, a dark centre.
     */
    private function drawSquare(int $cx, int $cy): void
    {
        for ($y = $cy - 2; $y <= $cy + 2; ++$y) {
            for ($x = $cx - 2; $x <= $cx + 2; ++$x) {
                $this->set($x, $y, 1 !== max(abs($x - $cx), abs($y - $cy)) ? 1 : 0);
            }
        }
    }

    /**
     * From version 7, the version protected by a BCH code, in two 6 × 3 blocks beside the top right and bottom left
     * finders.
     */
    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }

        $bits = self::bch($this->version, 6, 0x1F25, 12);

        for ($i = 0; $i < 18; ++$i) {
            $dark = ($bits >> $i) & 1;
            $a = $this->size() - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->set($a, $b, $dark);
            $this->set($b, $a, $dark);
        }
    }

    private function isReserved(int $index): bool
    {
        return true === ($this->reserved[$index] ?? false);
    }

    private function set(int $x, int $y, int $dark): void
    {
        $index = $y * $this->size() + $x;
        $this->modules[$index] = $dark;
        $this->reserved[$index] = true;
    }
}
