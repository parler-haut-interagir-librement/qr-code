<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use DateTimeInterface;
use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * A contact in the compact MeCard form: `MECARD:N:name;TEL:…;EMAIL:…;;`. Smaller than a vCard for the same contact,
 * and read by most phone cameras. Backslash, semicolon, comma, colon and double quote are escaped by a backslash.
 */
final readonly class MeCard implements Content
{
    public string $name;

    /** @var list<string> */
    public array $phones;

    /** @var list<string> */
    public array $emails;

    public string $url;

    public string $address;

    public string $note;

    /**
     * @param string       $name   the name, as it is shown
     * @param list<string> $phones
     * @param list<string> $emails
     *
     * @throws InvalidContent when a field is not clean UTF-8, the name is empty, or a number or an address is not one
     */
    public function __construct(
        string $name,
        array $phones = [],
        array $emails = [],
        string $url = '',
        string $address = '',
        string $note = '',
        public ?DateTimeInterface $birthday = null,
    ) {
        $this->name = Text::clean($name, 'name');

        if ('' === trim($this->name)) {
            throw InvalidContent::of('name', 'a text');
        }

        $this->phones = array_map(static fn (string $phone): string => Phone::number($phone, 'telephone number'), $phones);
        $this->emails = new Email($emails)->to;
        $this->url = Text::clean($url, 'address of the site');
        $this->address = Text::clean($address, 'postal address');
        $this->note = Text::clean($note, 'note');
    }

    #[Override]
    public function payload(): string
    {
        $fields = ['N:'.Text::field($this->name)];

        foreach ($this->phones as $phone) {
            $fields[] = 'TEL:'.Text::field($phone);
        }

        foreach ($this->emails as $email) {
            $fields[] = 'EMAIL:'.Text::field($email);
        }

        foreach (['URL' => $this->url, 'ADR' => $this->address, 'NOTE' => $this->note] as $name => $value) {
            if ('' !== $value) {
                $fields[] = $name.':'.Text::field($value);
            }
        }

        if ($this->birthday instanceof DateTimeInterface) {
            $fields[] = 'BDAY:'.$this->birthday->format('Ymd');
        }

        return 'MECARD:'.implode(';', $fields).';;';
    }

    #[Override]
    public function label(): string
    {
        return $this->name;
    }
}
