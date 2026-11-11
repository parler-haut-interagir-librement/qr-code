<?php

declare(strict_types=1);

namespace Phil\QrCode\Exception;

use InvalidArgumentException;

/**
 * A field of a typed content cannot be written: a text that is not UTF-8 or holds control characters, an address,
 * a number or a coordinate out of its form, a password too short for its network.
 */
final class InvalidContent extends InvalidArgumentException implements QrCodeException
{
    public static function of(string $field, string $expected): self
    {
        return new self(\sprintf('The %s must be %s.', $field, $expected));
    }
}
