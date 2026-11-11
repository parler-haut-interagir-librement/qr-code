<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use DateTimeInterface;
use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * A contact as a vCard 3.0 or 4.0: the formatted and structured names, the organisation and the title, the numbers,
 * the e-mail addresses, the sites, the postal address, the birthday and a note. Every text value escapes backslash,
 * comma, semicolon and line breaks; the lines end with CRLF and are folded before 75 octets, never inside a
 * character.
 */
final readonly class VCard implements Content
{
    public string $fullName;

    public string $familyName;

    public string $givenName;

    public string $organization;

    public string $title;

    /** @var list<string> */
    public array $phones;

    /** @var list<string> */
    public array $emails;

    /** @var list<string> */
    public array $urls;

    public string $note;

    /**
     * @param list<string> $phones
     * @param list<string> $emails
     * @param list<string> $urls
     *
     * @throws InvalidContent when a field is not clean UTF-8, the formatted name is empty, or a number, an e-mail
     *                        address or a site is not one
     */
    public function __construct(
        string $fullName,
        string $familyName = '',
        string $givenName = '',
        string $organization = '',
        string $title = '',
        array $phones = [],
        array $emails = [],
        array $urls = [],
        public ?PostalAddress $address = null,
        public ?DateTimeInterface $birthday = null,
        string $note = '',
        public VCardVersion $version = VCardVersion::V3,
    ) {
        $this->fullName = Text::clean($fullName, 'name');

        if ('' === trim($this->fullName)) {
            throw InvalidContent::of('name', 'a text');
        }

        $this->familyName = Text::clean($familyName, 'family name');
        $this->givenName = Text::clean($givenName, 'given name');
        $this->organization = Text::clean($organization, 'organization');
        $this->title = Text::clean($title, 'title');
        $this->phones = array_map(static fn (string $phone): string => Phone::number($phone, 'telephone number'), $phones);
        $this->emails = new Email($emails)->to;
        $this->urls = array_map(static fn (string $url): string => new Url($url)->payload(), $urls);
        $this->note = Text::clean($note, 'note', true);
    }

    #[Override]
    public function payload(): string
    {
        $v4 = VCardVersion::V4 === $this->version;
        $lines = ['BEGIN:VCARD', 'VERSION:'.$this->version->value];

        if (!$v4 || '' !== $this->familyName || '' !== $this->givenName) {
            $lines[] = 'N:'.Text::value($this->familyName).';'.Text::value($this->givenName).';;;';
        }

        $lines[] = 'FN:'.Text::value($this->fullName);

        if ('' !== $this->organization) {
            $lines[] = 'ORG:'.Text::value($this->organization);
        }

        if ('' !== $this->title) {
            $lines[] = 'TITLE:'.Text::value($this->title);
        }

        foreach ($this->phones as $phone) {
            $lines[] = $v4 ? 'TEL;VALUE=uri:tel:'.$phone : 'TEL:'.$phone;
        }

        foreach ($this->emails as $email) {
            $lines[] = 'EMAIL:'.Text::value($email);
        }

        foreach ($this->urls as $url) {
            $lines[] = 'URL:'.$url;
        }

        if ($this->address instanceof PostalAddress) {
            $lines[] = 'ADR:'.$this->address->value();
        }

        if ($this->birthday instanceof DateTimeInterface) {
            $lines[] = 'BDAY:'.$this->birthday->format($v4 ? 'Ymd' : 'Y-m-d');
        }

        if ('' !== $this->note) {
            $lines[] = 'NOTE:'.Text::value($this->note);
        }

        $lines[] = 'END:VCARD';

        return Text::lines($lines);
    }

    #[Override]
    public function label(): string
    {
        return $this->fullName;
    }
}
