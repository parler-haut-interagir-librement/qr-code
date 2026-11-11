<?php

declare(strict_types=1);

namespace Phil\QrCode;

/**
 * How a segment of a QR code is packed: digits by three in ten bits, the 45 characters of the alphanumeric set by two
 * in eleven bits, bytes by eight bits, the kanji of Shift JIS in thirteen bits each; or the ECI header, which names
 * the character set of the bytes after it.
 */
enum Mode: string
{
    case Numeric = 'numeric';
    case Alphanumeric = 'alphanumeric';
    case Byte = 'byte';
    case Kanji = 'kanji';
    case Eci = 'eci';

    /** The characters of the alphanumeric mode, in the order of their values. */
    public const string ALPHANUMERIC = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    /**
     * The densest of the numeric, alphanumeric and byte modes that holds the whole text.
     */
    public static function for(string $data): self
    {
        if (1 === preg_match('/^[0-9]*$/D', $data)) {
            return self::Numeric;
        }

        return 1 === preg_match('/^[0-9A-Z $%*+\-.\/:]*$/D', $data) ? self::Alphanumeric : self::Byte;
    }

    /**
     * The four bits that announce the mode.
     */
    public function indicator(): int
    {
        return match ($this) {
            self::Numeric => 0b0001,
            self::Alphanumeric => 0b0010,
            self::Byte => 0b0100,
            self::Kanji => 0b1000,
            self::Eci => 0b0111,
        };
    }

    /**
     * The width of the character count, which grows with the version: versions 1 to 9, 10 to 26, and 27 to 40. The
     * ECI header has no count.
     */
    public function countBits(int $version): int
    {
        $range = $version < 10 ? 0 : ($version < 27 ? 1 : 2);

        return match ($this) {
            self::Numeric => [10, 12, 14][$range],
            self::Alphanumeric => [9, 11, 13][$range],
            self::Byte => [8, 16, 16][$range],
            self::Kanji => [8, 10, 12][$range],
            self::Eci => 0,
        };
    }
}
