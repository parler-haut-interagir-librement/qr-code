<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Unit;

use Phil\QrCode\Content\Url;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\DataTooLong;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Internal\Capacity;
use Phil\QrCode\Internal\Encoder;
use Phil\QrCode\Internal\Penalty;
use Phil\QrCode\Internal\ReedSolomon;
use Phil\QrCode\Internal\Segmenter;
use Phil\QrCode\Internal\ShiftJis;
use Phil\QrCode\Internal\Symbol;
use Phil\QrCode\Mode;
use Phil\QrCode\QrCode;
use Phil\QrCode\Segment;
use Phil\QrCode\Tests\Support\Decoder;
use Phil\QrCode\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Codes read back by the reader of the tests, which shares nothing with the encoder but the block table — and which
 * reads the golden symbols ZXing read.
 */
#[CoversClass(QrCode::class)]
#[CoversClass(Encoder::class)]
#[CoversClass(Segmenter::class)]
#[CoversClass(Segment::class)]
#[CoversClass(Symbol::class)]
#[CoversClass(Capacity::class)]
#[CoversClass(ReedSolomon::class)]
#[CoversClass(Penalty::class)]
#[CoversClass(ShiftJis::class)]
#[CoversClass(Zone::class)]
#[CoversClass(DataTooLong::class)]
#[CoversClass(InvalidOption::class)]
final class RoundTripTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function texts(): iterable
    {
        yield 'an address' => ['https://example.com/tickets/42?seat=A12'];
        yield 'capitals and digits' => ['HTTPS://EXAMPLE.COM/T/12345678901234567890'];
        yield 'French' => ['Billet à 12 $ — rangée Ü, siège 7'];
        yield 'Japanese' => ['漢字とカタカナ、ひらがな。日本語のテキスト 2026年11月8日'];
        yield 'mixed scripts' => ['点茗 HELLO 12345678901234 abc ✓ 𠀋'];
        yield 'binary' => ["\x00\xFF\x80\x7F binary"];
        yield 'a long text' => [str_repeat('Ünïcødé 1234567 ABCDEF ', 10)];
        yield 'digits past version 9' => [str_repeat('9', 600)];
    }

    #[DataProvider('texts')]
    public function testReadsBackWhatItWrites(string $text): void
    {
        foreach (ErrorCorrection::cases() as $level) {
            $code = QrCode::encode($text, $level);

            self::assertSame($text, Decoder::text($code->rows()), $level->value);
            self::assertSame($level, $code->level);
            self::assertSame($text, $code->data);
        }
    }

    public function testAnnouncesUtf8ByAnEciHeader(): void
    {
        $code = QrCode::encode('Café');

        self::assertSame([['eci:26', ''], ['byte', 'Café']], Decoder::segments($code->rows()));
        self::assertSame([['byte', 'Café']], Decoder::segments(QrCode::encode('Café', eci: false)->rows()));
        self::assertSame([['byte', 'Cafe']], Decoder::segments(QrCode::encode('Cafe')->rows()));
    }

    public function testWritesKanjiUnlessTold(): void
    {
        self::assertSame([['kanji', '点茗']], Decoder::segments(QrCode::encode('点茗')->rows()));
        self::assertSame([['eci:26', ''], ['byte', '点茗']], Decoder::segments(QrCode::encode('点茗', kanji: false)->rows()));
    }

    public function testMixesSegmentsAndChoosesThemByVersion(): void
    {
        $text = 'HTTPS://EXAMPLE.COM/'.str_repeat('1', 40);
        $small = QrCode::encode($text, ErrorCorrection::Low);
        $large = QrCode::encode($text, ErrorCorrection::Low, 27);

        self::assertSame([Mode::Alphanumeric, Mode::Numeric], array_map(static fn (Segment $segment): Mode => $segment->mode, $small->segments));
        self::assertSame(2, $small->version);
        self::assertSame(27, $large->version);
        self::assertSame($text, Decoder::text($large->rows()));
    }

    public function testEncodesSegmentsOfTheCaller(): void
    {
        $segments = [Segment::eci(Segment::UTF8), Segment::bytes('Prix : '), Segment::numeric('1250'), Segment::kanji('円')];
        $code = QrCode::fromSegments($segments, ErrorCorrection::Quartile, 2, 3);

        self::assertSame($segments, $code->segments);
        self::assertSame('Prix : 1250円', $code->data);
        self::assertSame(2, $code->version);
        self::assertSame(3, $code->mask);
        self::assertSame(ErrorCorrection::Quartile, $code->level);
        self::assertSame([['eci:26', ''], ['byte', 'Prix : '], ['numeric', '1250'], ['kanji', '円']], Decoder::segments($code->rows()));
        self::assertSame(ErrorCorrection::Medium, QrCode::fromSegments([Segment::bytes('a')])->level);
        self::assertSame(1, QrCode::fromSegments([Segment::bytes('a')])->version);
    }

    public function testSkipsTheVersionsWhoseCountFieldIsTooNarrow(): void
    {
        // 256 bytes fit in version 9-L's codewords' worth of bits? No: 2 080 bits exceed its 1 856; and they cannot be
        // counted in eight bits anyway. Version 10 counts them in sixteen.
        $code = QrCode::fromSegments([Segment::bytes(str_repeat('a', 256))], ErrorCorrection::Low);

        self::assertSame(10, $code->version);
        self::assertSame(str_repeat('a', 256), Decoder::text($code->rows()));
    }

    public function testRefusesSegmentsBeyondVersion40(): void
    {
        $this->expectException(DataTooLong::class);
        $this->expectExceptionMessageIsOrContains('takes 23656 bits');

        QrCode::fromSegments([Segment::bytes(str_repeat('a', 2957))], ErrorCorrection::Low);
    }

    public function testRefusesSegmentsThatFillVersion40ToTheLastBit(): void
    {
        $this->expectException(DataTooLong::class);
        // Not refused before trying: the characters take 23 640 bits, the header 20 more.
        $this->expectExceptionMessageIsOrContains('takes 23660 bits');

        QrCode::fromSegments([Segment::bytes(str_repeat('a', 2955))], ErrorCorrection::Low);
    }

    /**
     * @return iterable<string, array{Segment, int}>
     */
    public static function justBeyondVersion40(): iterable
    {
        // The characters' bits alone fit in the 23 648 data bits of version 40-L, their header does not.
        yield '2956 bytes, exactly the data bits' => [Segment::bytes(str_repeat('a', 2956)), 23648 + 20];
        yield '7093 digits, 4 bits fewer' => [Segment::numeric(str_repeat('1', 7093)), 23644 + 18];
    }

    #[DataProvider('justBeyondVersion40')]
    public function testTriesTheSegmentsWhoseCharactersFitVersion40(Segment $segment, int $bits): void
    {
        $this->expectException(DataTooLong::class);
        $this->expectExceptionMessageIsOrContains('takes '.$bits.' bits');

        QrCode::fromSegments([$segment], ErrorCorrection::Low);
    }

    public function testEncodesTheLargestSegmentsVersion40Holds(): void
    {
        self::assertSame(40, QrCode::fromSegments([Segment::bytes(str_repeat('a', 2953))], ErrorCorrection::Low)->version);
    }

    public function testEncodesATypedContent(): void
    {
        $code = QrCode::encode(new Url('https://example.com/a b'));

        self::assertSame('https://example.com/a%20b', $code->data);
        self::assertSame('https://example.com/a%20b', Decoder::text($code->rows()));
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function shares(): iterable
    {
        yield 'a tenth' => [0.1];
        yield 'a fifth' => [0.2];
        yield 'a quarter' => [0.25];
        yield 'the largest' => [0.3];
    }

    #[DataProvider('shares')]
    public function testKeepsAZoneEveryBlockCanCorrect(float $share): void
    {
        foreach (['https://example.com/t/42', str_repeat('Ticket 1234567890 ', 12)] as $text) {
            $code = QrCode::encode($text, ErrorCorrection::Low, logoZone: $share);
            $zone = $code->zone;
            self::assertNotNull($zone);
            self::assertSame(ErrorCorrection::High, $code->level);
            self::assertSame(1, $zone->side % 2);
            self::assertGreaterThanOrEqual((int) ceil($share * $code->size()), $zone->side);
            self::assertLessThanOrEqual((int) ceil($share * $code->size()) + 1, $zone->side);
            self::assertSame($code->size() - $zone->side, 2 * $zone->x);
            self::assertSame($zone->x, $zone->y);
            self::assertGreaterThanOrEqual(9, $zone->x);

            $rows = $code->rows();
            $lost = [];

            for ($y = $zone->y; $y < $zone->y + $zone->side; ++$y) {
                $rows[$y] = substr_replace($rows[$y], str_repeat('0', $zone->side), $zone->x, $zone->side);

                for ($x = $zone->x; $x < $zone->x + $zone->side; ++$x) {
                    $lost[] = $y * $code->size() + $x;
                }
            }

            $read = Decoder::segments(array_values($rows), $lost);
            self::assertSame($text, implode('', array_column(array_filter($read, static fn (array $segment): bool => !str_starts_with($segment[0], 'eci')), 1)));
        }
    }

    public function testGrowsTheCodeUntilTheZoneIsCorrectable(): void
    {
        self::assertSame(1, QrCode::encode('A', ErrorCorrection::High)->version);
        self::assertSame(2, QrCode::encode('A', logoZone: 0.2)->version);
        self::assertSame(6, QrCode::encode('A', logoZone: 0.3)->version);
        self::assertNull(QrCode::encode('A')->zone);
        self::assertNull(Encoder::zone(1, ErrorCorrection::High, 0.2));
        // The first zone clear of the finders: nine modules from the edge.
        self::assertEquals(new Zone(9, 9, 3), Encoder::zone(1, ErrorCorrection::High, 0.1));
        self::assertNull(Encoder::zone(3, ErrorCorrection::High, 0.3));
        // Clear of the finders, but one block would lose more than a quarter of its correction.
        self::assertNull(Encoder::zone(3, ErrorCorrection::High, 0.2));
        self::assertEquals(new Zone(13, 13, 7), Encoder::zone(4, ErrorCorrection::High, 0.2));
        self::assertEquals(new Zone(14, 14, 13), Encoder::zone(6, ErrorCorrection::High, 0.3));
        self::assertEquals(new Zone(12, 12, 1), Encoder::zone(2, ErrorCorrection::High, 0.01));
    }

    public function testRefusesAZoneOutOfRange(): void
    {
        foreach ([-0.1, 0.31] as $share) {
            try {
                QrCode::encode('A', logoZone: $share);
                self::fail('A zone of '.$share.' is refused.');
            } catch (InvalidOption $invalidOption) {
                self::assertSame('The zone of the logo must be from 0 to 0.3 of the side; got '.$share.'.', $invalidOption->getMessage());
            }
        }

        self::assertNotNull(QrCode::encode('A', logoZone: QrCode::MAX_LOGO_ZONE)->zone);
    }

    public function testRaisesTheLevelBeforeFittingTheText(): void
    {
        self::assertSame(33, QrCode::encode(str_repeat('a', 2000), ErrorCorrection::Low)->version);

        $this->expectException(DataTooLong::class);
        $this->expectExceptionMessageIsOrContains('at level H');

        QrCode::encode(str_repeat('a', 2000), ErrorCorrection::Low, logoZone: 0.1);
    }

    public function testLaysTheCodewordsOfEachBlockAsTheReaderSplitsThem(): void
    {
        foreach ([1, 5, 7, 13, 20, 33, 40] as $version) {
            foreach (ErrorCorrection::cases() as $level) {
                self::assertSame(Decoder::owners($version, $level->value), Encoder::owners($version, $level), $version.'-'.$level->value);
            }
        }
    }

    public function testTellsWhetherAModuleLiesInTheZone(): void
    {
        $zone = new Zone(10, 12, 3);

        self::assertTrue($zone->contains(10, 12));
        self::assertTrue($zone->contains(12, 14));
        self::assertFalse($zone->contains(9, 12));
        self::assertFalse($zone->contains(10, 11));
        self::assertFalse($zone->contains(13, 12));
        self::assertFalse($zone->contains(10, 15));
    }
}
