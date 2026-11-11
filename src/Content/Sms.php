<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * A text message to send, as an `sms:` address of RFC 5724: the number, then the body percent-encoded.
 */
final readonly class Sms implements Content
{
    public string $number;

    public string $message;

    /**
     * @throws InvalidContent when the number is not a telephone number, or the message not clean UTF-8
     */
    public function __construct(string $number, string $message = '')
    {
        $this->number = Phone::number($number, 'number of the message');
        $this->message = Text::clean($message, 'message', true);
    }

    #[Override]
    public function payload(): string
    {
        return 'sms:'.$this->number.('' === $this->message ? '' : '?body='.Text::percent(str_replace("\n", "\r\n", $this->message)));
    }

    #[Override]
    public function label(): string
    {
        return $this->number;
    }
}
