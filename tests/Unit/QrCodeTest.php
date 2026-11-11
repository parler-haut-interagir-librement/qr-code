<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Unit;

use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\DataTooLong;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Internal\Capacity;
use Phil\QrCode\Internal\Encoder;
use Phil\QrCode\Internal\Penalty;
use Phil\QrCode\Internal\ReedSolomon;
use Phil\QrCode\Internal\Symbol;
use Phil\QrCode\Mode;
use Phil\QrCode\QrCode;
use Phil\QrCode\Segment;
use Phil\QrCode\Tests\Support\Decoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The golden symbols under Fixture/ were read back by an independent decoder (ZXing) when they were written, without
 * the ECI header, which they keep.
 */
#[CoversClass(QrCode::class)]
#[CoversClass(Mode::class)]
#[CoversClass(ErrorCorrection::class)]
#[CoversClass(Capacity::class)]
#[CoversClass(Encoder::class)]
#[CoversClass(ReedSolomon::class)]
#[CoversClass(Symbol::class)]
#[CoversClass(Penalty::class)]
#[CoversClass(DataTooLong::class)]
#[CoversClass(InvalidOption::class)]
#[CoversClass(Segment::class)]
final class QrCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ErrorCorrection, int, int, int, Mode}>
     */
    public static function goldens(): iterable
    {
        yield 'alphanumeric, version 1-Q' => ['hello-world-q', 'HELLO WORLD', ErrorCorrection::Quartile, 1, 1, 6, Mode::Alphanumeric];
        yield 'numeric, version 1-M' => ['digits-m', '01234567', ErrorCorrection::Medium, 1, 1, 0, Mode::Numeric];
        yield 'version information, 7-H' => ['abc-h-v7', 'ABC', ErrorCorrection::High, 7, 7, 0, Mode::Alphanumeric];
        yield 'UTF-8 bytes, version 5-H' => ['unicode-h', 'Bonjour à tous — ünïcødé ✓', ErrorCorrection::High, 1, 5, 4, Mode::Byte];
        yield 'an address, version 2-L' => ['url-l', 'https://ph-il.ca/a?b=c&d=é', ErrorCorrection::Low, 1, 2, 4, Mode::Byte];
    }

    #[DataProvider('goldens')]
    public function testEncodesTheGoldenSymbols(string $fixture, string $data, ErrorCorrection $level, int $minVersion, int $version, int $mask, Mode $mode): void
    {
        $code = QrCode::encode($data, $level, $minVersion, eci: false);
        $rows = file(__DIR__.'/Fixture/'.$fixture.'.txt', \FILE_IGNORE_NEW_LINES);

        self::assertSame($rows, $code->rows());
        self::assertSame($version, $code->version);
        self::assertSame($mask, $code->mask);
        self::assertSame([$mode], array_map(static fn (Segment $segment): Mode => $segment->mode, $code->segments));
        self::assertSame($data, Decoder::text($code->rows()));
        self::assertSame($level, $code->level);
        self::assertSame($data, $code->data);
        self::assertSame(17 + 4 * $version, $code->size());
    }

    public function testReadsModulesByColumnAndRow(): void
    {
        $code = QrCode::encode('HELLO WORLD', ErrorCorrection::Quartile);

        self::assertTrue($code->isDark(0, 0));
        self::assertFalse($code->isDark(7, 0));
        self::assertTrue($code->isDark(8, 13));
        self::assertFalse($code->isDark(21, 0));
        self::assertSame('1' === $code->rows()[9][12], $code->isDark(12, 9));
    }

    public function testKeepsTheMaskAsked(): void
    {
        $code = QrCode::encode('HELLO WORLD', ErrorCorrection::Quartile, 1, 3);

        self::assertSame(3, $code->mask);
        self::assertNotSame(QrCode::encode('HELLO WORLD', ErrorCorrection::Quartile)->rows(), $code->rows());
    }

    public function testDefaultsToMediumCorrection(): void
    {
        self::assertSame(ErrorCorrection::Medium, QrCode::encode('A')->level);
    }

    public function testEncodesAnEmptyText(): void
    {
        self::assertSame(1, QrCode::encode('')->version);
    }

    public function testGrowsToTheLargestVersion(): void
    {
        self::assertSame(40, QrCode::encode(str_repeat('a', 2953), ErrorCorrection::Low)->version);
        self::assertSame(40, QrCode::encode(str_repeat('7', 7089), ErrorCorrection::Low)->version);
    }

    public function testRefusesDataLongerThanVersion40(): void
    {
        $this->expectException(DataTooLong::class);
        $this->expectExceptionMessageIsOrContains('more than a QR code of version 40 holds at level L');

        QrCode::encode(str_repeat('a', 2954), ErrorCorrection::Low);
    }

    public function testRefusesAnyTextBeyondTheDigitLimitAtOnce(): void
    {
        $this->expectException(DataTooLong::class);
        $this->expectExceptionMessageIsOrContains('takes 21270 bits');

        QrCode::encode(str_repeat('7', 7090), ErrorCorrection::Low);
    }

    public function testRefusesAnAlphanumericTextTooLong(): void
    {
        $this->expectException(DataTooLong::class);
        $this->expectExceptionMessageIsOrContains('takes 23651 bits');

        QrCode::encode(str_repeat('A', 4296 + 1), ErrorCorrection::Low);
    }

    /**
     * @return iterable<array{int, ?int, string}>
     */
    public static function invalidOptions(): iterable
    {
        yield [0, null, 'The smallest version must be from 1 to 40; got 0.'];
        yield [41, null, 'The smallest version must be from 1 to 40; got 41.'];
        yield [1, -1, 'The mask must be from 0 to 7; got -1.'];
        yield [1, 8, 'The mask must be from 0 to 7; got 8.'];
    }

    #[DataProvider('invalidOptions')]
    public function testRefusesOptionsOutOfRange(int $minVersion, ?int $mask, string $message): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        QrCode::encode('A', ErrorCorrection::Low, $minVersion, $mask);
    }

    public function testAcceptsTheBoundsOfTheOptions(): void
    {
        self::assertSame(40, QrCode::encode('A', ErrorCorrection::Low, 40, 7)->version);
        self::assertSame(0, QrCode::encode('A', ErrorCorrection::Low, 1, 0)->mask);
    }

    public function testChoosesTheDensestMode(): void
    {
        self::assertSame(Mode::Numeric, Mode::for('0123'));
        self::assertSame(Mode::Numeric, Mode::for(''));
        self::assertSame(Mode::Alphanumeric, Mode::for('AB $%*+-./:9'));
        self::assertSame(Mode::Byte, Mode::for('ab'));
        self::assertSame(Mode::Byte, Mode::for("A\n"));
        self::assertSame(Mode::Byte, Mode::for("1\n"));
        self::assertSame(Mode::Byte, Mode::for('A,B'));
    }

    public function testWidensTheCharacterCountWithTheVersion(): void
    {
        self::assertSame([10, 10, 12, 12, 14], array_map(Mode::Numeric->countBits(...), [1, 9, 10, 26, 27]));
        self::assertSame([9, 11, 13], array_map(Mode::Alphanumeric->countBits(...), [9, 26, 40]));
        self::assertSame([8, 16, 16], array_map(Mode::Byte->countBits(...), [9, 10, 27]));
        self::assertSame([1, 2, 4], [Mode::Numeric->indicator(), Mode::Alphanumeric->indicator(), Mode::Byte->indicator()]);
    }

    public function testComputesTheCapacityOfEachVersion(): void
    {
        self::assertSame([26, 44, 196, 3706], array_map(Capacity::codewords(...), [1, 2, 7, 40]));
        self::assertSame([208, 359, 1568, 29648], array_map(Capacity::dataModules(...), [1, 2, 7, 40]));
        self::assertSame([19, 16, 13, 9], array_map(static fn (ErrorCorrection $level): int => Capacity::dataCodewords(1, $level), ErrorCorrection::cases()));
        self::assertSame(2956, Capacity::dataCodewords(40, ErrorCorrection::Low));
        self::assertSame(1276, Capacity::dataCodewords(40, ErrorCorrection::High));
    }

    public function testPlacesTheAlignmentPatterns(): void
    {
        self::assertSame([], Capacity::alignments(1));
        self::assertSame([6, 18], Capacity::alignments(2));
        self::assertSame([6, 22, 38], Capacity::alignments(7));
        self::assertSame([6, 34, 60, 86, 112, 138], Capacity::alignments(32));
        self::assertSame([6, 24, 50, 76, 102, 128, 154], Capacity::alignments(36));
        self::assertSame([6, 30, 58, 86, 114, 142, 170], Capacity::alignments(40));
    }

    public function testComputesTheErrorCorrectionCodewords(): void
    {
        [$version, $bits] = Encoder::fit(static fn (): array => [Segment::alphanumeric('HELLO WORLD')], ErrorCorrection::Quartile, 1);

        self::assertSame(1, $version);
        self::assertSame(
            [32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236, 168, 72, 22, 82, 217, 54, 156, 0, 46, 15, 180, 122, 16],
            Encoder::codewords($bits, $version, ErrorCorrection::Quartile),
        );
        self::assertSame(0, ReedSolomon::multiply(0, 7));
        self::assertSame(0, ReedSolomon::multiply(7, 0));
        self::assertSame(29, ReedSolomon::multiply(2, 128));
    }

    public function testProtectsTheFormatAndVersionInformation(): void
    {
        self::assertSame(0b110011000101111, Symbol::bch(0b01100, 5, 0x537, 10) ^ 0x5412);
        self::assertSame(0b101010000010010, Symbol::bch(0b00000, 5, 0x537, 10) ^ 0x5412);
        self::assertSame(0b000111110010010100, Symbol::bch(7, 6, 0x1F25, 12));
        self::assertSame(0b101000110001101001, Symbol::bch(40, 6, 0x1F25, 12));
    }

    public function testComputesEachMaskCondition(): void
    {
        $expected = [
            [true, false, true, true],
            [true, true, false, true],
            [true, false, true, false],
            [true, false, false, false],
            [true, true, false, false],
            [true, true, false, false],
            [true, true, false, false],
            [true, false, true, false],
        ];

        foreach ($expected as $mask => $values) {
            self::assertSame($values, [Symbol::masked($mask, 0, 0), Symbol::masked($mask, 1, 0), Symbol::masked($mask, 3, 1), Symbol::masked($mask, 2, 2)], 'mask '.$mask);
        }

        self::assertTrue(Symbol::masked(4, 2, 1));
        self::assertFalse(Symbol::masked(4, 3, 0));
        self::assertTrue(Symbol::masked(5, 6, 1));
        self::assertTrue(Symbol::masked(6, 2, 3));
        self::assertFalse(Symbol::masked(6, 3, 3));
        self::assertTrue(Symbol::masked(7, 2, 0));
        self::assertFalse(Symbol::masked(7, 1, 2));
    }

    public function testScoresEachRuleOfThePenalty(): void
    {
        // 1 × 1 dark: half off balance (10 × 9).
        self::assertSame(90, Penalty::of([1], 1));
        // A 5 × 5 light square: runs (3 × 10), squares (3 × 16), balance (10 × 9).
        self::assertSame(168, Penalty::of(array_fill(0, 25, 0), 5));
        // A checkerboard of 6 × 6: nothing but an even balance.
        $board = [];

        for ($i = 0; $i < 36; ++$i) {
            $board[] = (intdiv($i, 6) + $i % 6) % 2;
        }

        self::assertSame(0, Penalty::of($board, 6));
    }

    public function testCountsEachPartOfThePenalty(): void
    {
        self::assertSame(0, Penalty::runs('0000111101'));
        self::assertSame(3 + 5, Penalty::runs('00000101111111'));
        self::assertSame(1, Penalty::finders('110111010000'));
        self::assertSame(1, Penalty::finders('00001011101'));
        self::assertSame(2, Penalty::finders('000010111010000'));
        self::assertSame(0, Penalty::finders('1011101000'));
        self::assertSame(3, Penalty::squares(['110', '111', '010'], 3));
        self::assertSame(12, Penalty::squares(['000', '000', '000'], 3));
        self::assertSame(0, Penalty::balance([1, 0, 1, 0]));
        self::assertSame(0, Penalty::balance(array_merge(array_fill(0, 11, 1), array_fill(0, 9, 0))));
        self::assertSame(10, Penalty::balance(array_merge(array_fill(0, 11, 1), array_fill(0, 8, 0))));
    }
}
