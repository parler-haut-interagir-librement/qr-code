<?php

declare(strict_types=1);

namespace Phil\QrCode\Internal;

/**
 * The Reed-Solomon codewords of a block, over the field of 256 elements that QR codes use, reduced by the polynomial
 * x⁸ + x⁴ + x³ + x² + 1.
 *
 * @internal
 */
final class ReedSolomon
{
    /**
     * @var array{list<int>, array<int, int>}|null the powers of α, twice over so a sum of logarithms needs no
     *                                             reduction, and the logarithms of the non-zero elements; built once,
     *                                             never changed
     */
    private static ?array $tables = null;

    /**
     * The `$degree` codewords of error correction of the data codewords.
     *
     * @param list<int> $data
     *
     * @return list<int>
     */
    public static function remainder(array $data, int $degree): array
    {
        $generator = self::generator($degree);
        $remainder = array_fill(0, $degree, 0);

        foreach ($data as $codeword) {
            $factor = $codeword ^ array_shift($remainder);
            $remainder[] = 0;

            for ($i = 0; $i < $degree; ++$i) {
                $remainder[$i] ^= self::multiply($generator[$i], $factor);
            }
        }

        /** @var list<int> $remainder shifted and pushed once per codeword, it stays a list */
        return $remainder;
    }

    /**
     * The product of two elements of the field.
     */
    public static function multiply(int $a, int $b): int
    {
        if (0 === $a || 0 === $b) {
            return 0;
        }

        [$exp, $log] = self::tables();

        return $exp[$log[$a] + $log[$b]];
    }

    /**
     * The coefficients of (x − α⁰)(x − α¹)…(x − α^(degree−1)) below the leading one, highest power first.
     *
     * @return list<int>
     */
    private static function generator(int $degree): array
    {
        [$exp] = self::tables();
        $coefficients = [1];

        for ($root = 0; $root < $degree; ++$root) {
            $next = [...$coefficients, 0];

            foreach ($coefficients as $i => $coefficient) {
                $next[$i + 1] ^= self::multiply($coefficient, $exp[$root]);
            }

            $coefficients = $next;
        }

        return \array_slice($coefficients, 1);
    }

    /**
     * @return array{list<int>, array<int, int>}
     */
    private static function tables(): array
    {
        return self::$tables ??= self::build();
    }

    /**
     * @return array{list<int>, array<int, int>}
     */
    private static function build(): array
    {
        $exp = [];
        $log = [];
        $value = 1;

        for ($i = 0; $i < 255; ++$i) {
            $exp[$i] = $value;
            $log[$value] = $i;
            $value <<= 1;

            if (1 === $value >> 8) {
                $value ^= 0x11D;
            }
        }

        return [[...$exp, ...$exp], $log];
    }
}
