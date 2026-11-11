<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * An address to open. Only the schemes allowed — `https` and `http` unless told otherwise — are written, never
 * `javascript:` or `data:`; spaces, characters beyond ASCII and the characters RFC 3986 does not allow in an address
 * are percent-encoded. The host must already be ASCII: an internationalised name is written in its IDNA form.
 */
final readonly class Url implements Content
{
    private string $payload;

    /**
     * @param list<string> $schemes the schemes allowed, in lower case
     *
     * @throws InvalidContent when the address has no host, a scheme not allowed, or a host beyond ASCII
     */
    public function __construct(
        public string $url,
        array $schemes = ['https', 'http'],
    ) {
        $url = Text::clean($url, 'address');
        $scheme = strtolower((string) strstr($url, ':', true));

        if (!\in_array($scheme, $schemes, true) || 1 !== preg_match('#^[a-z][a-z0-9+.\-]*://[^/?\#]+#i', $url, $match)) {
            throw InvalidContent::of('address', 'absolute, with a host, of the schemes '.implode(', ', $schemes));
        }

        // The text is clean: a byte beyond ASCII, or a space, is all the scheme and host may still hold.
        if (1 === preg_match('/[^\x21-\x7E]/', $match[0])) {
            throw InvalidContent::of('host of the address', 'ASCII: write an internationalised name in its IDNA form');
        }

        $this->payload = (string) preg_replace_callback(
            '/[^\x21-\x7E]|["<>\\\\^`{|}]/',
            static fn (array $byte): string => \sprintf('%%%02X', \ord($byte[0])),
            $url,
        );
    }

    #[Override]
    public function payload(): string
    {
        return $this->payload;
    }

    #[Override]
    public function label(): string
    {
        return $this->url;
    }
}
