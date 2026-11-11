<?php

declare(strict_types=1);

namespace Phil\QrCode;

/**
 * The square at the centre of a code kept for a logo: its modules are drawn light and the logo over them, the error
 * correction of the code making up for them. Positions are in modules from the top left corner of the symbol, quiet
 * zone excluded.
 */
final readonly class Zone
{
    public function __construct(
        public int $x,
        public int $y,
        public int $side,
    ) {
    }

    /**
     * Whether the module of a column and a row lies in the zone.
     */
    public function contains(int $x, int $y): bool
    {
        return $x >= $this->x && $x < $this->x + $this->side && $y >= $this->y && $y < $this->y + $this->side;
    }
}
