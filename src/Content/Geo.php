<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * A place, as a `geo:` address of RFC 5870 in WGS 84: latitude, longitude, an altitude in metres and an uncertainty
 * in metres when known, each written in decimals to seven places at most, never in exponent form.
 */
final readonly class Geo implements Content
{
    public string $name;

    /**
     * @param string $name what `label()` shows instead of the coordinates; not written in the code
     *
     * @throws InvalidContent when a coordinate is out of its range or not finite
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?float $altitude = null,
        public ?float $uncertainty = null,
        string $name = '',
    ) {
        if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) {
            throw InvalidContent::of('latitude', 'from -90 to 90 degrees');
        }

        if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) {
            throw InvalidContent::of('longitude', 'from -180 to 180 degrees');
        }

        if (null !== $altitude && (!is_finite($altitude) || abs($altitude) > 100_000_000)) {
            throw InvalidContent::of('altitude', 'a finite number of metres');
        }

        if (null !== $uncertainty && (!is_finite($uncertainty) || $uncertainty < 0 || $uncertainty > 100_000_000)) {
            throw InvalidContent::of('uncertainty', 'a positive number of metres');
        }

        $this->name = Text::clean($name, 'name of the place');
    }

    #[Override]
    public function payload(): string
    {
        $coordinates = [$this->decimal($this->latitude), $this->decimal($this->longitude)];

        if (null !== $this->altitude) {
            $coordinates[] = $this->decimal($this->altitude);
        }

        return 'geo:'.implode(',', $coordinates).(null === $this->uncertainty ? '' : ';u='.$this->decimal($this->uncertainty));
    }

    #[Override]
    public function label(): string
    {
        return '' === $this->name ? $this->decimal($this->latitude).', '.$this->decimal($this->longitude) : $this->name;
    }

    private function decimal(float $value): string
    {
        $text = rtrim(rtrim(\sprintf('%.7F', $value), '0'), '.');

        return '-0' === $text ? '0' : $text;
    }
}
