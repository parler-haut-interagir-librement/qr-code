<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Unit;

use Phil\QrCode\Internal\Segmenter;
use Phil\QrCode\Internal\ShiftJis;
use Phil\QrCode\Mode;
use Phil\QrCode\Segment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Segmenter::class)]
#[CoversClass(Segment::class)]
#[CoversClass(ShiftJis::class)]
final class SegmenterTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function texts(): iterable
    {
        foreach (['A1', '12A', 'a1234', '1234a', 'ABC12345', 'AB1C2', 'a.b:C', '0123456A', 'x点茗y', '点1', '9点', 'é1234567', 'ab12CD', 'A1B2C3D', '1a1a1', 'HTTP://A.B/123', 'Ünï12', '点茗点', '　A　'] as $text) {
            yield $text => [$text];
        }
    }

    /**
     * Every assignment of a mode to each character, within the range of versions, gives no fewer bits.
     */
    #[DataProvider('texts')]
    public function testFindsTheShortestSequenceOfSegments(string $text): void
    {
        foreach ([1, 10, 27] as $version) {
            foreach ([[true, true], [false, true], [true, false], [false, false]] as [$eci, $kanji]) {
                $segments = Segmenter::split($text, $version, $eci, $kanji);

                self::assertSame($this->shortest($text, $version, $eci, $kanji), $this->length($segments, $version), $text.' at version '.$version);
                self::assertSame($text, implode('', array_map(static fn (Segment $segment): string => $segment->text, $segments)));
            }
        }
    }

    public function testChoosesTheModes(): void
    {
        self::assertSame([['alphanumeric', 'HTTPS://EXAMPLE.COM/'], ['numeric', '123456789012345678901234']], $this->modes('HTTPS://EXAMPLE.COM/123456789012345678901234'));
        self::assertSame([['byte', 'https://example.com/12']], $this->modes('https://example.com/12'));
        self::assertSame([['byte', 'https://example.com/'], ['numeric', '1234']], $this->modes('https://example.com/1234'));
        self::assertSame([['numeric', '']], $this->modes(''));
        self::assertSame([['eci', ''], ['byte', 'é']], $this->modes('é'));
        self::assertSame([['byte', 'é']], $this->modes('é', eci: false));
        self::assertSame([['kanji', '点茗']], $this->modes('点茗'));
        self::assertSame([['eci', ''], ['byte', '点茗']], $this->modes('点茗', kanji: false));
        self::assertSame([['byte', "\xFF\x80点"]], $this->modes("\xFF\x80点"));
        self::assertSame([['byte', 'Sweep 1 ✓']], $this->modes('Sweep 1 ✓', eci: false));
    }

    /**
     * The ECI header is paid once, whatever the number of byte segments beyond ASCII.
     */
    public function testPaysTheEciHeaderOnce(): void
    {
        $segments = Segmenter::split('é'.str_repeat('1', 30).'é', 1, true, true);

        self::assertSame(['eci', 'byte', 'numeric', 'byte'], array_map(static fn (Segment $segment): string => $segment->mode->value, $segments));
        self::assertSame(Segment::UTF8, bindec($segments[0]->bits));
    }

    /**
     * @return list<array{string, string}>
     */
    private function modes(string $text, bool $eci = true, bool $kanji = true): array
    {
        return array_map(static fn (Segment $segment): array => [$segment->mode->value, $segment->text], Segmenter::split($text, 1, $eci, $kanji));
    }

    /**
     * @param list<Segment> $segments
     */
    private function length(array $segments, int $version): int
    {
        return array_sum(array_map(static fn (Segment $segment): int => \strlen($segment->encode($version)), $segments));
    }

    /**
     * The fewest bits of any assignment, found by trying them all.
     */
    private function shortest(string $text, int $version, bool $eci, bool $kanji): int
    {
        $characters = mb_str_split($text, 1, 'UTF-8');
        $choices = [];

        foreach ($characters as $character) {
            $modes = [Mode::Byte];

            if (ctype_digit($character)) {
                $modes[] = Mode::Numeric;
            }

            if (1 === \strlen($character) && str_contains(Mode::ALPHANUMERIC, $character)) {
                $modes[] = Mode::Alphanumeric;
            }

            if ($kanji && null !== ShiftJis::kanji($character)) {
                $modes[] = Mode::Kanji;
            }

            $choices[] = $modes;
        }

        $best = \PHP_INT_MAX;

        foreach ($this->assignments($choices) as $assignment) {
            $segments = [];
            $run = '';

            foreach ($characters as $i => $character) {
                if ($i > 0 && $assignment[$i] !== $assignment[$i - 1]) {
                    $segments[] = $this->segment($assignment[$i - 1], $run);
                    $run = '';
                }

                $run .= $character;
            }

            $segments[] = $this->segment($assignment[\count($characters) - 1], $run);
            $length = $this->length($segments, $version);

            if ($eci && array_any($segments, static fn (Segment $segment): bool => Mode::Byte === $segment->mode && 1 === preg_match('/[\x80-\xff]/', $segment->text))) {
                $length += 12;
            }

            $best = min($best, $length);
        }

        return $best;
    }

    /**
     * Every way to pick one of the modes of each character.
     *
     * @param list<list<Mode>> $choices
     *
     * @return list<list<Mode>>
     */
    private function assignments(array $choices): array
    {
        $assignments = [[]];

        foreach ($choices as $modes) {
            $next = [];

            foreach ($assignments as $assignment) {
                foreach ($modes as $mode) {
                    $next[] = [...$assignment, $mode];
                }
            }

            $assignments = $next;
        }

        return $assignments;
    }

    private function segment(Mode $mode, string $run): Segment
    {
        return match ($mode) {
            Mode::Numeric => Segment::numeric($run),
            Mode::Alphanumeric => Segment::alphanumeric($run),
            Mode::Kanji => Segment::kanji($run),
            default => Segment::bytes($run),
        };
    }
}
