<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Support;

use RuntimeException;

/**
 * The bit stream of a symbol read back into its segments, for the reader of the tests.
 */
final class SegmentParser
{
    private const string ALPHANUMERIC = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    private int $at = 0;

    private function __construct(
        private readonly string $bits,
        private readonly int $range,
    ) {
    }

    /**
     * @param list<int> $data
     *
     * @return list<array{string, string}>
     */
    public static function parse(array $data, int $version): array
    {
        $bits = implode('', array_map(static fn (int $byte): string => str_pad(decbin($byte), 8, '0', \STR_PAD_LEFT), $data));
        $parser = new self($bits, $version < 10 ? 0 : ($version < 27 ? 1 : 2));
        $segments = [];

        while ($parser->at + 4 <= \strlen($bits)) {
            $mode = $parser->read(4);

            if (0 === $mode) {
                break;
            }

            $segments[] = match ($mode) {
                0b0111 => ['eci:'.$parser->eci(), ''],
                0b0001 => ['numeric', $parser->numeric()],
                0b0010 => ['alphanumeric', $parser->alphanumeric()],
                0b0100 => ['byte', $parser->bytes()],
                0b1000 => ['kanji', $parser->kanji()],
                default => throw new RuntimeException('Unknown mode '.$mode.'.'),
            };
        }

        return $segments;
    }

    private function read(int $width): int
    {
        $value = (int) bindec(substr($this->bits, $this->at, $width));
        $this->at += $width;

        return $value;
    }

    private function eci(): int
    {
        $first = $this->read(8);

        if (0 === ($first & 0x80)) {
            return $first;
        }

        return 0x80 === ($first & 0xC0) ? ($first & 0x3F) << 8 | $this->read(8) : ($first & 0x1F) << 16 | $this->read(16);
    }

    private function numeric(): string
    {
        $text = '';

        for ($left = $this->read([10, 12, 14][$this->range]); $left > 0; $left -= 3) {
            $digits = min(3, $left);
            $text .= str_pad((string) $this->read([4, 7, 10][$digits - 1]), $digits, '0', \STR_PAD_LEFT);
        }

        return $text;
    }

    private function alphanumeric(): string
    {
        $text = '';

        for ($left = $this->read([9, 11, 13][$this->range]); $left > 0; $left -= 2) {
            if ($left >= 2) {
                $pair = $this->read(11);
                $text .= self::ALPHANUMERIC[intdiv($pair, 45)].self::ALPHANUMERIC[$pair % 45];
            } else {
                $text .= self::ALPHANUMERIC[$this->read(6)];
            }
        }

        return $text;
    }

    private function bytes(): string
    {
        $text = '';

        for ($count = $this->read([8, 16, 16][$this->range]); $count > 0; --$count) {
            $text .= \chr($this->read(8) & 0xFF);
        }

        return $text;
    }

    private function kanji(): string
    {
        $sjis = '';

        for ($count = $this->read([8, 10, 12][$this->range]); $count > 0; --$count) {
            $value = $this->read(13);
            $code = intdiv($value, 0xC0) << 8 | $value % 0xC0;
            $code += $code < 0x1F00 ? 0x8140 : 0xC140;
            $sjis .= pack('n', $code);
        }

        return mb_convert_encoding($sjis, 'UTF-8', 'SJIS');
    }
}
