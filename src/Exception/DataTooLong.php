<?php

declare(strict_types=1);

namespace Phil\QrCode\Exception;

use InvalidArgumentException;
use Phil\QrCode\ErrorCorrection;

/**
 * The data does not fit in the largest QR code, version 40, at the level of error correction asked.
 */
final class DataTooLong extends InvalidArgumentException implements QrCodeException
{
    public static function at(int $bits, ErrorCorrection $level): self
    {
        return new self(\sprintf('The data takes %d bits, more than a QR code of version 40 holds at level %s; shorten it or lower the error correction.', $bits, $level->value));
    }
}
