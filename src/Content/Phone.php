<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * A number to call, as a `tel:` address of RFC 3966: a `+` and the digits of a global number, or the digits of a
 * local one, with the visual separators `-`, `.`, `(` and `)`; spaces are dropped.
 */
final readonly class Phone implements Content
{
    public string $number;

    /**
     * @throws InvalidContent when the number holds anything else, or no digit
     */
    public function __construct(string $number)
    {
        $this->number = self::number($number, 'telephone number');
    }

    /**
     * The number without its spaces, when it has the form of RFC 3966.
     *
     * @throws InvalidContent
     */
    public static function number(string $number, string $field): string
    {
        $compact = str_replace(' ', '', Text::clean($number, $field));

        if (1 !== preg_match('/^\+?[0-9().\-]{0,31}[0-9][0-9().\-]{0,31}$/', $compact)) {
            throw InvalidContent::of($field, 'digits, a leading + and the separators - . ( )');
        }

        return $compact;
    }

    #[Override]
    public function payload(): string
    {
        return 'tel:'.$this->number;
    }

    #[Override]
    public function label(): string
    {
        return $this->number;
    }
}
