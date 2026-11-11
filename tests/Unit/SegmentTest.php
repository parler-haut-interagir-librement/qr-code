<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Unit;

use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Internal\ShiftJis;
use Phil\QrCode\Mode;
use Phil\QrCode\Segment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Segment::class)]
#[CoversClass(Mode::class)]
#[CoversClass(ShiftJis::class)]
#[CoversClass(InvalidOption::class)]
final class SegmentTest extends TestCase
{
    public function testWritesTheKanjiOfTheStandardsExample(): void
    {
        // ISO/IEC 18004, the kanji mode: 点 is 0x935F, 茗 0xE4AA.
        $segment = Segment::kanji('点茗');

        self::assertSame(Mode::Kanji, $segment->mode);
        self::assertSame(2, $segment->count);
        self::assertSame('01101100111111101010101010', $segment->bits);
        self::assertSame('10000000001001101100111111101010101010', $segment->encode(1));
        self::assertSame('10000000000010', substr($segment->encode(10), 0, 14));
        self::assertSame('1000000000000010', substr($segment->encode(27), 0, 16));
        self::assertSame('点茗', $segment->text);
    }

    public function testComputesTheValueOfEachKanjiRange(): void
    {
        self::assertSame(0, ShiftJis::kanji('　'));
        self::assertSame(0x1AAA, ShiftJis::kanji('茗'));
        self::assertSame(0xD9F, ShiftJis::kanji('点'));
        self::assertSame(1, ShiftJis::kanji('、'));
        self::assertSame(0x1E * 0xC0 + 0xBC, ShiftJis::kanji('滌'));
        self::assertSame(0x29 * 0xC0 + 0x64, ShiftJis::kanji('熙'));
        self::assertSame(0x1F * 0xC0, ShiftJis::kanji('漾'));
        self::assertNull(ShiftJis::kanji('a'));
        self::assertNull(ShiftJis::kanji('é'));
        self::assertNull(ShiftJis::kanji('—'));
        self::assertNull(ShiftJis::kanji('ｱ'));
        self::assertNull(ShiftJis::kanji('𠀋'));
    }

    public function testWritesTheEciHeaderInOneTwoOrThreeBytes(): void
    {
        self::assertSame('011100011010', Segment::eci(Segment::UTF8)->encode(1));
        self::assertSame('011100011010', Segment::eci(26)->encode(40));
        self::assertSame('00000000', Segment::eci(0)->bits);
        self::assertSame('01111111', Segment::eci(127)->bits);
        self::assertSame('1000000010000000', Segment::eci(128)->bits);
        self::assertSame('1011111111111111', Segment::eci(16_383)->bits);
        self::assertSame('110000000100000000000000', Segment::eci(16_384)->bits);
        self::assertSame('110'.\sprintf('%021b', 999_999), Segment::eci(999_999)->bits);
        self::assertSame(Mode::Eci, Segment::eci(3)->mode);
        self::assertSame(0, Segment::eci(3)->count);
        self::assertSame('', Segment::eci(3)->text);
        self::assertSame(0, Mode::Eci->countBits(1));
    }

    public function testWritesEachModeWithItsIndicatorAndCount(): void
    {
        self::assertSame('000100000000110001111011', Segment::numeric('123')->encode(1));
        self::assertSame('001000000001000111001110', Segment::alphanumeric('AC')->encode(9));
        self::assertSame('0100000000000000000101100001', Segment::bytes('a')->encode(10));
        self::assertSame('01000000000101100001', Segment::bytes('a')->encode(9));
        self::assertSame('', Segment::numeric('')->bits);
        self::assertSame('', Segment::alphanumeric('')->bits);
        self::assertSame('', Segment::bytes('')->bits);
        self::assertSame('', Segment::kanji('')->bits);
        self::assertSame('00010000000000', Segment::numeric('')->encode(1));
        self::assertSame('101100', Segment::alphanumeric(':')->bits);
        self::assertSame('0000', Segment::numeric('0')->bits);
        self::assertSame('1100011', Segment::numeric('99')->bits);
        self::assertSame('1111100111', Segment::numeric('999')->bits);
    }

    /**
     * @return iterable<string, array{callable(): Segment, string}>
     */
    public static function invalid(): iterable
    {
        yield 'a letter among digits' => [static fn (): Segment => Segment::numeric('12a'), 'The text of a numeric segment must be digits only; got "12a".'];
        yield 'a digit and a line break' => [static fn (): Segment => Segment::numeric("1\n"), 'must be digits only'];
        yield 'a small letter' => [static fn (): Segment => Segment::alphanumeric('Ab'), 'The text of an alphanumeric segment must be digits, capitals, space and $%*+-./:; got "Ab".'];
        yield 'a line break' => [static fn (): Segment => Segment::alphanumeric("A\n"), 'alphanumeric segment'];
        yield 'a letter among kanji' => [static fn (): Segment => Segment::kanji('点a'), 'The text of a kanji segment must be double-byte Shift JIS characters only; got "a".'];
        yield 'bytes that are not UTF-8' => [static fn (): Segment => Segment::kanji("\xFF"), 'The text of a kanji segment must be UTF-8; got other bytes.'];
        yield 'a negative assignment' => [static fn (): Segment => Segment::eci(-1), 'The ECI assignment must be from 0 to 999999; got -1.'];
        yield 'an assignment too large' => [static fn (): Segment => Segment::eci(1_000_000), 'got 1000000'];
        yield 'a long text, shortened' => [static fn (): Segment => Segment::numeric(str_repeat('x', 50)), '"'.str_repeat('x', 39).'…"'];
    }

    /**
     * @param callable(): Segment $build
     */
    #[DataProvider('invalid')]
    public function testRefusesCharactersOutsideTheMode(callable $build, string $message): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testQuotesInvalidBytesAsReplacementCharacters(): void
    {
        try {
            Segment::numeric("\xFF");
            self::fail('A byte that is no digit is refused.');
        } catch (InvalidOption $invalidOption) {
            self::assertStringContainsString('got "?"', $invalidOption->getMessage());
        }
    }
}
