<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Unit;

use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Internal\Capacity;
use Phil\QrCode\Internal\Encoder;
use Phil\QrCode\Internal\Penalty;
use Phil\QrCode\Internal\ReedSolomon;
use Phil\QrCode\Internal\Symbol;
use Phil\QrCode\QrCode;
use Phil\QrCode\Segment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * `Fixture/sweep.json` holds one symbol per version, each mask forced in turn and each level, by the hash of its rows;
 * every one was read back by an independent decoder (ZXing) when the file was written.
 */
#[CoversClass(QrCode::class)]
#[CoversClass(Symbol::class)]
#[CoversClass(Encoder::class)]
#[CoversClass(Capacity::class)]
#[CoversClass(ReedSolomon::class)]
#[CoversClass(Penalty::class)]
#[CoversClass(Segment::class)]
final class SymbolTest extends TestCase
{
    public function testDrawsEveryVersionUnderEveryMask(): void
    {
        $sweep = json_decode((string) file_get_contents(__DIR__.'/Fixture/sweep.json'), true, 4, \JSON_THROW_ON_ERROR);
        self::assertIsArray($sweep);
        self::assertCount(40, $sweep);

        foreach ($sweep as $case) {
            self::assertIsArray($case);
            self::assertIsString($case['data']);
            self::assertIsString($case['level']);
            self::assertIsInt($case['version']);
            self::assertIsInt($case['mask']);
            $code = QrCode::encode($case['data'], ErrorCorrection::from($case['level']), max(1, min(40, $case['version'])), $case['mask'], eci: false);

            self::assertSame($case['version'], $code->version);
            self::assertSame($case['sha'], hash('sha256', implode("\n", $code->rows())), 'version '.$case['version']);
        }
    }

    public function testWritesTheBitsOfEachMode(): void
    {
        self::assertSame('000111101101110010000111', Segment::numeric('1234567')->bits);
        self::assertSame('00011110110111000', Segment::numeric('12356')->bits);
        self::assertSame('00111001110101001', Segment::alphanumeric('AC-')->bits);
        self::assertSame('011000011100001110101001', Segment::bytes('aé')->bits);
        $segments = [Segment::numeric('1234567')];
        self::assertSame([1, '00010000000111000111101101110010000111', $segments, null], Encoder::fit(static fn (): array => $segments, ErrorCorrection::Low, 1));
    }

    public function testEndsAndPadsTheData(): void
    {
        [$version, $bits] = Encoder::fit(static fn (): array => [Segment::numeric('12')], ErrorCorrection::Medium, 1);

        // 21 bits, four zeros, three more to the byte, then the pad codewords.
        self::assertSame([16, 8, 96, 0, 236, 17, 236, 17, 236, 17, 236, 17, 236, 17, 236, 17], \array_slice(Encoder::codewords($bits, $version, ErrorCorrection::Medium), 0, 16));

        // One bit left: a single zero ends the data.
        [$version, $bits] = Encoder::fit(static fn (): array => [Segment::numeric(str_repeat('7', 41))], ErrorCorrection::Low, 1);
        self::assertSame(1, $version);
        self::assertSame(151, \strlen($bits));
        self::assertCount(26, Encoder::codewords($bits, $version, ErrorCorrection::Low));
        self::assertSame(154, Encoder::codewords($bits, $version, ErrorCorrection::Low)[18]);
    }

    public function testMovesToTheNextVersionOneBitTooLong(): void
    {
        self::assertSame(2, QrCode::encode(str_repeat('A', 21), ErrorCorrection::Medium)->version);
        self::assertSame(1, QrCode::encode(str_repeat('A', 20), ErrorCorrection::Medium)->version);
        self::assertSame(3, QrCode::encode(str_repeat('A', 21), ErrorCorrection::High)->version);
    }

    public function testChoosesTheFirstMaskOfTheLowestPenalty(): void
    {
        self::assertSame(7, QrCode::encode('T10', ErrorCorrection::Low)->mask);
        self::assertSame(0, QrCode::encode('T46', ErrorCorrection::Low)->mask, 'masks 0 and 7 tie');
    }

    public function testAddsEachRuleOfThePenalty(): void
    {
        $modules = [];

        for ($i = 0; $i < 121; ++$i) {
            $modules[] = (int) (0 === ($i * 7 + intdiv($i, 11) * 3) % 5 || 0 === $i % 4);
        }

        array_splice($modules, 0, 11, array_map(intval(...), str_split('10111010000')));
        $rows = array_map(static fn (array $row): string => implode('', $row), array_chunk($modules, 11));
        $columns = [];

        for ($x = 0; $x < 11; ++$x) {
            $columns[] = implode('', array_column(array_map(str_split(...), $rows), $x));
        }

        $expected = Penalty::squares($rows, 11) + Penalty::balance($modules);

        foreach ([...$rows, ...$columns] as $line) {
            $expected += Penalty::runs($line) + 40 * Penalty::finders($line);
        }

        self::assertGreaterThan(0, Penalty::finders($rows[0]));
        self::assertSame($expected, Penalty::of($modules, 11));
        self::assertSame(30, Penalty::balance([1, 1, 1, 1, 0, 0]));
    }
}
