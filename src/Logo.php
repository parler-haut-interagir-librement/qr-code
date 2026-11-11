<?php

declare(strict_types=1);

namespace Phil\QrCode;

use Phil\QrCode\Exception\InvalidOption;

/**
 * An image drawn over the zone a code keeps at its centre: PNG, JPEG, WebP or SVG, recognised by its first bytes,
 * never by a file name. `SvgWriter` embeds it as a data URI in an `<image>`, where an SVG runs no script;
 * `PngWriter` draws PNG, JPEG and WebP logos with GD.
 */
final readonly class Logo
{
    /** The largest logo read, in bytes. */
    public const int MAX_BYTES = 1_048_576;

    private function __construct(
        public string $bytes,
        public string $mediaType,
    ) {
    }

    /**
     * @throws InvalidOption when the bytes are empty, larger than 1 MiB, or of none of the four formats
     */
    public static function fromBytes(string $bytes): self
    {
        if (\strlen($bytes) > self::MAX_BYTES) {
            throw InvalidOption::of('logo', 'at most 1 MiB', \strlen($bytes).' bytes');
        }

        $type = match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, 'RIFF') && 'WEBP' === substr($bytes, 8, 4) => 'image/webp',
            1 === preg_match('/^(?:\xEF\xBB\xBF)?\s*(?:<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*)*(?:<!DOCTYPE svg[^>]*>\s*)?<svg[\s>]/s', $bytes) => 'image/svg+xml',
            default => throw InvalidOption::of('logo', 'a PNG, JPEG, WebP or SVG image', 'other bytes'),
        };

        return new self($bytes, $type);
    }

    /**
     * @throws InvalidOption when the file cannot be read, or is not a logo `fromBytes()` accepts
     */
    public static function fromFile(string $path): self
    {
        $size = is_file($path) ? filesize($path) : false;

        if (false === $size) {
            throw InvalidOption::of('logo', 'a readable file', '"'.$path.'"');
        }

        // Refused before it is read.
        if ($size > self::MAX_BYTES) {
            throw InvalidOption::of('file of the logo', 'at most 1 MiB', $size.' bytes');
        }

        return self::fromBytes((string) file_get_contents($path));
    }

    /**
     * The logo as a `data:` URI, base64.
     */
    public function dataUri(): string
    {
        return 'data:'.$this->mediaType.';base64,'.sodium_bin2base64($this->bytes, \SODIUM_BASE64_VARIANT_ORIGINAL);
    }
}
