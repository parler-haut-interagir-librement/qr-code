<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * A message to write, as a `mailto:` address of RFC 6068: the recipients, then the subject, the body and the copies,
 * each percent-encoded; line breaks of the body are sent as CRLF.
 */
final readonly class Email implements Content
{
    /** @var list<string> */
    public array $to;

    /** @var list<string> */
    public array $cc;

    /** @var list<string> */
    public array $bcc;

    public string $subject;

    public string $body;

    /**
     * @param string|list<string> $to
     * @param list<string>        $cc
     * @param list<string>        $bcc
     *
     * @throws InvalidContent when an address is not an e-mail address, or a text is not clean UTF-8
     */
    public function __construct(string|array $to, string $subject = '', string $body = '', array $cc = [], array $bcc = [])
    {
        $this->to = $this->addresses(\is_string($to) ? [$to] : $to, 'recipient');
        $this->cc = $this->addresses($cc, 'copy');
        $this->bcc = $this->addresses($bcc, 'blind copy');
        $this->subject = Text::clean($subject, 'subject');
        $this->body = Text::clean($body, 'body', true);
    }

    #[Override]
    public function payload(): string
    {
        $fields = array_filter([
            'subject' => Text::percent($this->subject),
            'body' => Text::percent(str_replace("\n", "\r\n", $this->body)),
            'cc' => implode(',', array_map($this->address(...), $this->cc)),
            'bcc' => implode(',', array_map($this->address(...), $this->bcc)),
        ], static fn (string $value): bool => '' !== $value);
        $query = implode('&', array_map(static fn (string $name, string $value): string => $name.'='.$value, array_keys($fields), $fields));

        return 'mailto:'.implode(',', array_map($this->address(...), $this->to)).('' === $query ? '' : '?'.$query);
    }

    #[Override]
    public function label(): string
    {
        return implode(', ', $this->to);
    }

    /**
     * @param list<string> $addresses
     *
     * @return list<string>
     */
    private function addresses(array $addresses, string $field): array
    {
        foreach ($addresses as $address) {
            if (\strlen($address) > 254 || false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                throw InvalidContent::of($field, 'an e-mail address');
            }
        }

        return $addresses;
    }

    private function address(string $address): string
    {
        return Text::percent($address, "!$'()*+;=:@");
    }
}
