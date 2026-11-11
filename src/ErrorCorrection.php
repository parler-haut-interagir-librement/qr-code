<?php

declare(strict_types=1);

namespace Phil\QrCode;

/**
 * How much of a QR code may be lost — dirt, a fold, a logo printed over it — and still be read: about 7 % of its
 * codewords at `Low`, 15 % at `Medium`, 25 % at `Quartile` and 30 % at `High`. More correction means a larger code
 * for the same data.
 */
enum ErrorCorrection: string
{
    case Low = 'L';
    case Medium = 'M';
    case Quartile = 'Q';
    case High = 'H';

    /**
     * The two bits of the level in the format information of the symbol.
     */
    public function formatBits(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 0,
            self::Quartile => 3,
            self::High => 2,
        };
    }
}
