<?php

declare(strict_types=1);

namespace Phil\QrCode\Internal;

use Phil\QrCode\Exception\InvalidContent;

/**
 * The checks and the escapes the typed contents share: clean UTF-8, the TEXT values of vCard and iCalendar with their
 * folded lines, the values of MeCard and Wi-Fi codes, and percent-encoding.
 *
 * @internal
 */
final class Text
{
    /** The longest field accepted, in bytes: more than any QR code holds. */
    public const int MAX_BYTES = 4096;

    /**
     * The value, its line breaks made `\n`, when it is UTF-8 of at most 4096 bytes without control characters — line
     * breaks and tabs allowed in a multi-line field.
     *
     * @throws InvalidContent
     */
    public static function clean(string $value, string $field, bool $multiline = false): string
    {
        if (\strlen($value) > self::MAX_BYTES || !mb_check_encoding($value, 'UTF-8')) {
            throw InvalidContent::of($field, 'UTF-8 text of at most 4096 bytes');
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $forbidden = $multiline ? '/[\x00-\x08\x0B-\x1F\x7F\x{80}-\x{9F}]/u' : '/[\x00-\x1F\x7F\x{80}-\x{9F}]/u';

        if (1 === preg_match($forbidden, $value)) {
            throw InvalidContent::of($field, $multiline ? 'text without control characters but line breaks and tabs' : 'text on one line, without control characters');
        }

        return $value;
    }

    /**
     * A TEXT value of vCard and iCalendar: backslash, comma and semicolon escaped by a backslash, line breaks as
     * `\n`.
     */
    public static function value(string $value): string
    {
        return str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\;', '\\n'], $value);
    }

    /**
     * Content lines joined by CRLF, each folded before 75 octets by a CRLF and a space, never inside a UTF-8
     * character.
     *
     * @param list<string> $lines
     */
    public static function lines(array $lines): string
    {
        $folded = [];

        foreach ($lines as $line) {
            $parts = [];
            $limit = 75;

            while (\strlen($line) > $limit) {
                $cut = $limit;

                // A continuation byte is never the first of a part.
                while (0x80 === (\ord($line[$cut]) & 0xC0)) {
                    --$cut;
                }

                $parts[] = substr($line, 0, $cut);
                $line = substr($line, $cut);
                // The space that starts a folded line counts in its 75 octets.
                $limit = 74;
            }

            $parts[] = $line;
            $folded[] = implode("\r\n ", $parts);
        }

        return implode("\r\n", $folded);
    }

    /**
     * A value of a MeCard or a Wi-Fi code: backslash, semicolon, comma, colon and double quote escaped by a backslash.
     */
    public static function field(string $value): string
    {
        return (string) preg_replace('/([\\\;,:"])/', '\\\\$1', $value);
    }

    /**
     * Every byte but the unreserved characters of RFC 3986 and those of `$keep` percent-encoded.
     */
    public static function percent(string $value, string $keep = ''): string
    {
        return (string) preg_replace_callback(
            '/[^A-Za-z0-9\-._~'.preg_quote($keep, '/').']/',
            static fn (array $match): string => \sprintf('%%%02X', \ord($match[0])),
            $value,
        );
    }
}
