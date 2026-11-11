<?php

declare(strict_types=1);

namespace Phil\QrCode\Exception;

use InvalidArgumentException;

/**
 * An option of the encoder or of the SVG writer is out of its range.
 */
final class InvalidOption extends InvalidArgumentException implements QrCodeException
{
    public static function of(string $name, string $expected, string $given): self
    {
        return new self(\sprintf('The %s must be %s; got %s.', $name, $expected, $given));
    }
}
