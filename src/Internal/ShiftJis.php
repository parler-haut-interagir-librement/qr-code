<?php

declare(strict_types=1);

namespace Phil\QrCode\Internal;

/**
 * The thirteen bits of a kanji, from its Shift JIS code: 0x8140 to 0x9FFC less 0x8140, or 0xE040 to 0xEBBF less
 * 0xC140, then the high byte times 0xC0 plus the low one.
 *
 * @internal
 */
final class ShiftJis
{
    /**
     * The value of one UTF-8 character in the kanji mode, or null when JIS X 0208 has no double-byte code for it in
     * those two ranges.
     */
    public static function kanji(string $character): ?int
    {
        $sjis = mb_convert_encoding($character, 'SJIS', 'UTF-8');

        if (2 !== \strlen($sjis) || mb_convert_encoding($sjis, 'UTF-8', 'SJIS') !== $character) {
            return null;
        }

        $code = \ord($sjis[0]) << 8 | \ord($sjis[1]);

        if ($code >= 0x8140 && $code <= 0x9FFC) {
            $code -= 0x8140;
        } elseif ($code >= 0xE040 && $code <= 0xEBBF) {
            $code -= 0xC140;
        } else {
            return null;
        }

        return ($code >> 8) * 0xC0 + ($code & 0xFF);
    }
}
