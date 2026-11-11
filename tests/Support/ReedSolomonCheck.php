<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Support;

use RuntimeException;

/**
 * The Reed-Solomon side of the reader of the tests, over GF(256) reduced by x⁸ + x⁴ + x³ + x² + 1: the syndromes of a
 * block, and the lost codewords of a block solved from them.
 */
final class ReedSolomonCheck
{
    /** @var list<int> */
    private static array $exp = [];

    /** @var array<int, int> */
    private static array $log = [];

    /**
     * S_i = c(α^i), the codeword polynomial at each root of the generator, highest power first.
     *
     * @param list<int> $block
     *
     * @return list<int>
     */
    public static function syndromes(array $block, int $ec): array
    {
        $syndromes = [];

        for ($i = 0; $i < $ec; ++$i) {
            $value = 0;

            foreach ($block as $c) {
                $value = self::mul($value, self::power($i)) ^ $c;
            }

            $syndromes[] = $value;
        }

        return $syndromes;
    }

    /**
     * The lost codewords of a block found from its syndromes, by Gaussian elimination over GF(256): with the lost
     * values unknown, each syndrome is a linear equation in them.
     *
     * @param list<int> $block
     * @param list<int> $lost
     *
     * @return list<int>
     */
    public static function solve(array $block, int $ec, array $lost): array
    {
        if ([] === $lost) {
            return $block;
        }

        if (\count($lost) > $ec) {
            throw new RuntimeException('More codewords lost than the block can correct.');
        }

        $n = \count($block);

        $block = array_map(static fn (int $c, int $place): int => \in_array($place, $lost, true) ? 0 : $c, $block, array_keys($block));

        $known = self::syndromes($block, $ec);
        $matrix = [];

        for ($i = 0; $i < $ec; ++$i) {
            $row = [];

            foreach ($lost as $place) {
                $row[] = self::power($i * ($n - 1 - $place) % 255);
            }

            $row[] = $known[$i];
            $matrix[] = $row;
        }

        $unknowns = \count($lost);

        for ($col = 0, $r = 0; $col < $unknowns; ++$col) {
            $pivot = $r;

            while ($pivot < $ec && 0 === $matrix[$pivot][$col]) {
                ++$pivot;
            }

            if ($pivot === $ec) {
                throw new RuntimeException('The lost codewords cannot be solved.');
            }

            [$matrix[$r], $matrix[$pivot]] = [$matrix[$pivot], $matrix[$r]];
            $inverse = self::power(255 - self::$log[$matrix[$r][$col]]);
            $matrix[$r] = array_map(static fn (int $v): int => self::mul($v, $inverse), $matrix[$r]);

            for ($other = 0; $other < $ec; ++$other) {
                if ($other !== $r && 0 !== $matrix[$other][$col]) {
                    $factor = $matrix[$other][$col];

                    foreach ($matrix[$other] as $k => $v) {
                        $matrix[$other][$k] = $v ^ self::mul($factor, $matrix[$r][$k]);
                    }
                }
            }

            ++$r;
        }

        $solved = array_combine($lost, array_map(static fn (array $row): int => $row[$unknowns], \array_slice($matrix, 0, $unknowns)));

        return array_map(static fn (int $c, int $place): int => $solved[$place] ?? $c, $block, array_keys($block));
    }

    private static function power(int $exponent): int
    {
        self::tables();

        return self::$exp[$exponent % 255];
    }

    private static function mul(int $a, int $b): int
    {
        self::tables();

        return 0 === $a || 0 === $b ? 0 : self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    private static function tables(): void
    {
        if ([] !== self::$exp) {
            return;
        }

        $value = 1;

        for ($i = 0; $i < 255; ++$i) {
            self::$exp[] = $value;
            self::$log[$value] = $i;
            $value = ($value << 1) ^ (0 !== ($value & 0x80) ? 0x11D : 0);
        }
    }
}
