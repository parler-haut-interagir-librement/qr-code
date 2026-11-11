<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * The postal address of a vCard, in the components of its `ADR` property.
 */
final readonly class PostalAddress
{
    public string $street;

    public string $locality;

    public string $region;

    public string $postalCode;

    public string $country;

    /**
     * @throws InvalidContent when a component is not clean UTF-8 on one line
     */
    public function __construct(string $street = '', string $locality = '', string $region = '', string $postalCode = '', string $country = '')
    {
        $this->street = Text::clean($street, 'street of the address');
        $this->locality = Text::clean($locality, 'locality of the address');
        $this->region = Text::clean($region, 'region of the address');
        $this->postalCode = Text::clean($postalCode, 'postal code of the address');
        $this->country = Text::clean($country, 'country of the address');
    }

    /**
     * The seven components of `ADR`, the post office box and the extended address left empty, each escaped.
     */
    public function value(): string
    {
        return implode(';', array_map(Text::value(...), ['', '', $this->street, $this->locality, $this->region, $this->postalCode, $this->country]));
    }
}
