<?php

declare(strict_types=1);

namespace Phil\QrCode\Internal;

use Phil\QrCode\Mode;
use Phil\QrCode\Segment;

/**
 * The shortest sequence of segments for a text at a range of versions, found by dynamic programming over its
 * characters.
 *
 * Each state is the mode of the segment the last character went into, with the characters left in an unfinished
 * group of digits or of alphanumeric characters, and whether the ECI header of UTF-8 is already paid. A character
 * costs its exact bits in the mode of its state — a digit 4, 3 then 3, an alphanumeric character 6 then 5, a byte 8,
 * a kanji 13 —, and entering a mode costs its indicator and count: the bits counted are the bits written.
 *
 * A text that is not valid UTF-8 is written as bytes, without ECI and without kanji.
 *
 * @internal
 */
final class Segmenter
{
    /** The states: numeric with 0, 1 or 2 digits in its group, alphanumeric with 0 or 1, byte, kanji. */
    private const array MODES = [Mode::Numeric, Mode::Numeric, Mode::Numeric, Mode::Alphanumeric, Mode::Alphanumeric, Mode::Byte, Mode::Kanji];

    /** The bits of the ECI header of UTF-8: its indicator and an assignment below 128. */
    private const int ECI_BITS = 12;

    /**
     * @param int<1, 40> $version any version of the range whose count fields are used
     *
     * @return list<Segment>
     */
    public static function split(string $data, int $version, bool $eci, bool $kanji): array
    {
        if ('' === $data) {
            return [Segment::numeric('')];
        }

        $utf8 = mb_check_encoding($data, 'UTF-8');
        $characters = $utf8 ? mb_str_split($data, 1, 'UTF-8') : str_split($data);
        $modes = self::modes($characters, $version, $utf8 && $eci, $utf8 && $kanji);
        $segments = [];
        $run = '';
        $mode = null;

        foreach ($characters as $i => $character) {
            if (null !== $mode && $modes[$i] !== $mode) {
                $segments[] = self::segment($mode, $run);
                $run = '';
            }

            $mode = $modes[$i];
            $run .= $character;
        }

        $segments[] = self::segment($mode, $run);
        $needsEci = $utf8 && $eci && array_any($segments, static fn (Segment $segment): bool => Mode::Byte === $segment->mode && 1 === preg_match('/[\x80-\xff]/', $segment->text));

        return $needsEci ? [Segment::eci(Segment::UTF8), ...$segments] : $segments;
    }

    /**
     * The mode of each character on the cheapest path.
     *
     * @param non-empty-list<string> $characters
     *
     * @return array<int, Mode> the mode of each character, by its index
     */
    private static function modes(array $characters, int $version, bool $eci, bool $kanji): array
    {
        $headers = array_map(static fn (Mode $mode): int => 4 + $mode->countBits($version), self::MODES);
        // State s + 7 is state s with the ECI header paid; before the first character, only the start.
        $costs = [0];
        $back = [];

        foreach ($characters as $i => $character) {
            [$costs, $back[]] = self::advance($costs, self::steps($character, $kanji, $eci), $headers, 0 === $i);
        }

        $state = 0;
        $best = \PHP_INT_MAX;

        foreach ($costs as $candidate => $cost) {
            if ($cost < $best) {
                $state = $candidate;
                $best = $cost;
            }
        }

        $modes = [];

        for ($i = \count($characters) - 1; $i >= 0; --$i) {
            $modes[$i] = self::MODES[$state % 7];
            $state = $back[$i][$state];
        }

        return $modes;
    }

    /**
     * The cheapest cost of each state after one more character, and the state each one comes from.
     *
     * @param array<int, int>                  $costs
     * @param list<array{int, ?int, int, int}> $steps
     * @param list<int>                        $headers
     *
     * @return array{array<int, int>, array<int, int>}
     */
    private static function advance(array $costs, array $steps, array $headers, bool $first): array
    {
        $next = [];
        $from = [];

        foreach ($steps as [$target, $entry, $continued, $paid]) {
            foreach ($costs as $state => $cost) {
                $added = self::added($state % 7, $target, $first, $entry, $continued, $headers[$target]);

                if (null === $added) {
                    continue;
                }

                $index = $target + ($state >= 7 || $paid > 0 ? 7 : 0);
                $total = $cost + $added + ($state >= 7 ? 0 : $paid);

                if (!isset($next[$index]) || $total < $next[$index]) {
                    $next[$index] = $total;
                    $from[$index] = $state;
                }
            }
        }

        return [$next, $from];
    }

    /**
     * What a character costs from a state, or null when it cannot follow it: within the segment, the bits it adds to
     * it; else the header of a new segment and the bits of its first character.
     */
    private static function added(int $state, int $target, bool $first, ?int $entry, int $continued, int $header): ?int
    {
        if (!$first && self::MODES[$state] === self::MODES[$target]) {
            return self::follows($state, $target) ? $continued : null;
        }

        return null === $entry ? null : $header + $entry;
    }

    /**
     * Each state a character may go to: the state, its cost when a segment starts with it — null for the states a
     * segment cannot start in —, its cost when the segment goes on, and the ECI header it calls for.
     *
     * @return list<array{int, ?int, int, int}>
     */
    private static function steps(string $character, bool $kanji, bool $eci): array
    {
        $steps = [];

        if (1 === \strlen($character) && ctype_digit($character)) {
            $steps[] = [1, 4, 4, 0];
            $steps[] = [2, null, 3, 0];
            $steps[] = [0, null, 3, 0];
        }

        if (1 === \strlen($character) && str_contains(Mode::ALPHANUMERIC, $character)) {
            $steps[] = [4, 6, 6, 0];
            $steps[] = [3, null, 5, 0];
        }

        $steps[] = [5, 8 * \strlen($character), 8 * \strlen($character), $eci && 1 === preg_match('/[\x80-\xff]/', $character) ? self::ECI_BITS : 0];

        if ($kanji && \strlen($character) > 1 && null !== ShiftJis::kanji($character)) {
            $steps[] = [6, 13, 13, 0];
        }

        return $steps;
    }

    /**
     * Whether a character may go on from one state to another of the same mode: a digit fills the group after the
     * last, an alphanumeric character likewise; bytes and kanji go on from themselves.
     */
    private static function follows(int $state, int $target): bool
    {
        return match ($target) {
            0 => 2 === $state,
            1 => 0 === $state,
            2 => 1 === $state,
            3 => 4 === $state,
            4 => 3 === $state,
            default => $state === $target,
        };
    }

    private static function segment(Mode $mode, string $run): Segment
    {
        return match ($mode) {
            Mode::Numeric => Segment::numeric($run),
            Mode::Alphanumeric => Segment::alphanumeric($run),
            Mode::Kanji => Segment::kanji($run),
            default => Segment::bytes($run),
        };
    }
}
