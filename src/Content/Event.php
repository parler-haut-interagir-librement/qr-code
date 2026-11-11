<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * An event to add to a calendar, as an iCalendar `VEVENT` (RFC 5545): its summary, its start and end — instants in
 * UTC, or dates for an event of whole days, the end date excluded —, its place, its description and its address.
 * Text values escape backslash, comma, semicolon and line breaks; lines end with CRLF and are folded before 75
 * octets.
 */
final readonly class Event implements Content
{
    public string $summary;

    public string $location;

    public string $description;

    public string $url;

    public string $uid;

    /**
     * @param bool $allDay whether the event lasts whole days: its start and end are written as the dates they fall on
     *                     in their own time zone
     *
     * @throws InvalidContent when the summary is empty, a text is not clean UTF-8, the end is not after the start, or
     *                        the address is not one
     */
    public function __construct(
        string $summary,
        public DateTimeInterface $start,
        public ?DateTimeInterface $end = null,
        string $location = '',
        string $description = '',
        string $url = '',
        public bool $allDay = false,
        string $uid = '',
    ) {
        $this->summary = Text::clean($summary, 'summary');

        if ('' === trim($this->summary)) {
            throw InvalidContent::of('summary', 'a text');
        }

        if ($end instanceof DateTimeInterface && $this->sortable($end) <= $this->sortable($start)) {
            throw InvalidContent::of('end of the event', 'after its start');
        }

        $this->location = Text::clean($location, 'location');
        $this->description = Text::clean($description, 'description', true);
        $this->url = '' === $url ? '' : new Url($url)->payload();
        $this->uid = Text::clean($uid, 'identifier of the event');
    }

    #[Override]
    public function payload(): string
    {
        $lines = ['BEGIN:VEVENT'];

        if ('' !== $this->uid) {
            $lines[] = 'UID:'.Text::value($this->uid);
        }

        $lines[] = 'SUMMARY:'.Text::value($this->summary);
        $lines[] = 'DTSTART'.$this->moment($this->start);

        if ($this->end instanceof DateTimeInterface) {
            $lines[] = 'DTEND'.$this->moment($this->end);
        }

        foreach (['LOCATION' => $this->location, 'DESCRIPTION' => $this->description] as $name => $value) {
            if ('' !== $value) {
                $lines[] = $name.':'.Text::value($value);
            }
        }

        if ('' !== $this->url) {
            $lines[] = 'URL:'.$this->url;
        }

        $lines[] = 'END:VEVENT';

        return Text::lines($lines);
    }

    #[Override]
    public function label(): string
    {
        return $this->summary;
    }

    /**
     * The day of an event of whole days, or the instant of another in UTC, as digits of one length that sort as
     * they compare.
     */
    private function sortable(DateTimeInterface $moment): string
    {
        return $this->allDay ? $moment->format('Ymd') : DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('YmdHisu');
    }

    private function moment(DateTimeInterface $moment): string
    {
        if ($this->allDay) {
            return ';VALUE=DATE:'.$moment->format('Ymd');
        }

        return ':'.DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }
}
