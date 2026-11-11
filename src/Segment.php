<?php

declare(strict_types=1);

namespace Phil\QrCode;

use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Internal\ShiftJis;

/**
 * A run of the data written in one mode: its mode indicator, its character count and its characters packed in that
 * mode; or an ECI header, which names the character set of the bytes that follow it.
 *
 * `QrCode::encode()` finds the shortest sequence of segments for a text; `QrCode::fromSegments()` takes a sequence
 * built by the caller.
 */
final readonly class Segment
{
    /** The ECI assignment of UTF-8. */
    public const int UTF8 = 26;

    /**
     * @param string $text  the characters of the segment as UTF-8, or its bytes; empty for an ECI header
     * @param int    $count the characters the count field announces: digits, characters, bytes or kanji
     * @param string $bits  the characters packed in the mode, `0` and `1`, without the indicator and the count
     */
    private function __construct(
        public Mode $mode,
        public string $text,
        public int $count,
        public string $bits,
    ) {
    }

    /**
     * Digits, three in ten bits, two in seven, one in four.
     *
     * @throws InvalidOption when the text holds anything but digits
     */
    public static function numeric(string $digits): self
    {
        if (1 !== preg_match('/^[0-9]*$/D', $digits)) {
            throw InvalidOption::of('text of a numeric segment', 'digits only', self::quote($digits));
        }

        $bits = '';

        foreach ('' === $digits ? [] : str_split($digits, 3) as $group) {
            $bits .= self::bits((int) $group, 3 * \strlen($group) + 1);
        }

        return new self(Mode::Numeric, $digits, \strlen($digits), $bits);
    }

    /**
     * Characters of the alphanumeric set, two in eleven bits, one in six.
     *
     * @throws InvalidOption when the text holds a character outside the set
     */
    public static function alphanumeric(string $text): self
    {
        if (1 !== preg_match('/^[0-9A-Z $%*+\-.\/:]*$/D', $text)) {
            throw InvalidOption::of('text of an alphanumeric segment', 'digits, capitals, space and $%*+-./:', self::quote($text));
        }

        $bits = '';

        foreach ('' === $text ? [] : str_split($text, 2) as $pair) {
            $bits .= 2 === \strlen($pair)
                ? self::bits(self::value($pair[0]) * 45 + self::value($pair[1]), 11)
                : self::bits(self::value($pair), 6);
        }

        return new self(Mode::Alphanumeric, $text, \strlen($text), $bits);
    }

    /**
     * Bytes, eight bits each: UTF-8 text after an ECI header of UTF-8, or any binary data.
     */
    public static function bytes(string $bytes): self
    {
        $bits = '';

        foreach ('' === $bytes ? [] : str_split($bytes) as $byte) {
            $bits .= self::bits(\ord($byte[0]), 8);
        }

        return new self(Mode::Byte, $bytes, \strlen($bytes), $bits);
    }

    /**
     * Kanji and the other double-byte characters of Shift JIS, thirteen bits each.
     *
     * @throws InvalidOption when a character of the UTF-8 text has no double-byte Shift JIS code
     */
    public static function kanji(string $text): self
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw InvalidOption::of('text of a kanji segment', 'UTF-8', 'other bytes');
        }

        $bits = '';
        $characters = mb_str_split($text, 1, 'UTF-8');

        foreach ($characters as $character) {
            $value = ShiftJis::kanji($character);

            if (null === $value) {
                throw InvalidOption::of('text of a kanji segment', 'double-byte Shift JIS characters only', self::quote($character));
            }

            $bits .= self::bits($value, 13);
        }

        return new self(Mode::Kanji, $text, \count($characters), $bits);
    }

    /**
     * The ECI header of an assignment — `Segment::UTF8` for UTF-8 — in eight, sixteen or twenty-four bits.
     *
     * @throws InvalidOption when the assignment is out of 0 to 999999
     */
    public static function eci(int $assignment): self
    {
        $bits = match (true) {
            $assignment < 0 || $assignment > 999_999 => throw InvalidOption::of('ECI assignment', 'from 0 to 999999', (string) $assignment),
            $assignment < 128 => self::bits($assignment, 8),
            $assignment < 16_384 => '10'.self::bits($assignment, 14),
            default => '110'.self::bits($assignment, 21),
        };

        return new self(Mode::Eci, '', 0, $bits);
    }

    /**
     * The bits of the segment in a version: indicator, count, characters.
     */
    public function encode(int $version): string
    {
        $count = Mode::Eci === $this->mode ? '' : self::bits($this->count, $this->mode->countBits($version));

        return self::bits($this->mode->indicator(), 4).$count.$this->bits;
    }

    /**
     * The `$width` low bits of a value, most significant first.
     */
    public static function bits(int $value, int $width): string
    {
        return str_pad(decbin($value), $width, '0', \STR_PAD_LEFT);
    }

    private static function value(string $character): int
    {
        return (int) strpos(Mode::ALPHANUMERIC, $character);
    }

    private static function quote(string $text): string
    {
        return '"'.mb_strimwidth(mb_scrub($text, 'UTF-8'), 0, 40, '…', 'UTF-8').'"';
    }
}
