<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Unit;

use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Logo;
use Phil\QrCode\QrCode;
use Phil\QrCode\SvgWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SvgWriter::class)]
#[CoversClass(InvalidOption::class)]
final class SvgWriterTest extends TestCase
{
    public function testDrawsEachDarkRunInOnePath(): void
    {
        $svg = new SvgWriter()->write(QrCode::encode('HELLO WORLD', ErrorCorrection::Quartile));

        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 29 29" role="img" shape-rendering="crispEdges"><title>HELLO WORLD</title><rect width="29" height="29" fill="#ffffff"/><path fill="#000000" d="M4 4h7v1h-7zM15 4h1v1h-1zM18 4h7v1h-7z', $svg);
        self::assertStringEndsWith('z"/></svg>', $svg);
        self::assertSame(1, substr_count($svg, '<path'));
    }

    public function testCountsEveryDarkModuleOnce(): void
    {
        $code = QrCode::encode('https://ph-il.ca', ErrorCorrection::Medium);
        preg_match_all('/h(\d+)v1/', new SvgWriter(0)->write($code), $runs);

        self::assertSame(substr_count(implode('', $code->rows()), '1'), array_sum(array_map(intval(...), $runs[1])));
    }

    public function testWritesTheOptionsAndAnEscapedTitle(): void
    {
        $svg = new SvgWriter(0, '#123456', '#ABCDEF')->write(QrCode::encode('A'), 'Tom & "Jerry" <3');

        self::assertStringContainsString('viewBox="0 0 21 21"', $svg);
        self::assertStringContainsString('<title>Tom &amp; &quot;Jerry&quot; &lt;3</title>', $svg);
        self::assertStringContainsString('fill="#ABCDEF"/><path fill="#123456" d="M0 0h7v1h-7z', $svg);
    }

    public function testEscapesForXml(): void
    {
        self::assertStringContainsString('<title>Tom&apos;s</title>', new SvgWriter()->write(QrCode::encode('A'), "Tom's"));
        self::assertStringContainsString('<title>'."\u{FFFD}".'</title>', new SvgWriter()->write(QrCode::encode('A'), "\xff"));
    }

    public function testEscapesTheDataUsedAsTitle(): void
    {
        self::assertStringContainsString('<title>a&lt;b&gt;</title>', new SvgWriter()->write(QrCode::encode('a<b>')));
    }

    public function testAcceptsTheBoundsOfTheQuietZone(): void
    {
        self::assertStringContainsString('viewBox="0 0 53 53"', new SvgWriter(16)->write(QrCode::encode('A')));
    }

    public function testWritesTheSizeAsked(): void
    {
        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 29 29" width="200" height="200" role="img"', new SvgWriter(size: 200)->write(QrCode::encode('A')));
        self::assertStringContainsString('width="4096" height="4096"', new SvgWriter(size: 4096)->write(QrCode::encode('A')));
        self::assertStringContainsString('viewBox="0 0 29 29" role="img"', new SvgWriter(size: 0)->write(QrCode::encode('A')));
    }

    public function testLeavesTheZoneLightAndDrawsTheLogoOverIt(): void
    {
        $code = QrCode::encode('https://example.com/t/42', logoZone: 0.25);
        $zone = $code->zone;
        self::assertNotNull($zone);
        $logo = Logo::fromFile(__DIR__.'/../Fixtures/logo.svg');
        $svg = new SvgWriter(2)->write($code, 'Ticket', $logo);
        preg_match_all('/M(\d+) (\d+)h(\d+)/', $svg, $runs, \PREG_SET_ORDER);

        foreach ($runs as [, $x, $y, $length]) {
            for ($i = 0; $i < (int) $length; ++$i) {
                self::assertFalse($zone->contains((int) $x - 2 + $i, (int) $y - 2), 'no dark module in the zone');
            }
        }

        $dark = 0;

        for ($y = 0; $y < $code->size(); ++$y) {
            for ($x = 0; $x < $code->size(); ++$x) {
                $dark += $code->isDark($x, $y) && !$zone->contains($x, $y) ? 1 : 0;
            }
        }

        self::assertSame($dark, array_sum(array_map(static fn (array $run): int => (int) $run[3], $runs)));
        self::assertStringEndsWith(\sprintf('<image x="%1$d.5" y="%1$d.5" width="%2$d" height="%2$d" preserveAspectRatio="xMidYMid meet" href="%3$s"/></svg>', $zone->x + 2, $zone->side - 1, $logo->dataUri()), $svg);
        self::assertStringNotContainsString('<image', new SvgWriter()->write($code));
    }

    public function testRefusesALogoWithoutAZone(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('The code of a logo must be encoded with a zone for it; got a code without one.');

        new SvgWriter()->write(QrCode::encode('A'), '', Logo::fromFile(__DIR__.'/../Fixtures/logo.svg'));
    }

    /**
     * @return iterable<array{0: int, 1: string, 2: string, 3: string, 4?: int}>
     */
    public static function invalid(): iterable
    {
        yield [-1, '#000000', '#ffffff', 'The quiet zone must be from 0 to 16 modules; got -1.'];
        yield [17, '#000000', '#ffffff', 'The quiet zone must be from 0 to 16 modules; got 17.'];
        yield [4, 'black', '#ffffff', 'The dark colour must be a colour written #rrggbb; got "black".'];
        yield [4, '#000000', '#fff', 'The light colour must be a colour written #rrggbb; got "#fff".'];
        yield [4, "#000000\n", '#ffffff', 'The dark colour must be a colour written #rrggbb'];
        yield [4, '#00000g', '#ffffff', 'The dark colour must be a colour written #rrggbb'];
        yield [4, '#000000', '#ffffff', 'The size must be from 0 to 4096 pixels; got -1.', -1];
        yield [4, '#000000', '#ffffff', 'got 4097', 4097];
    }

    #[DataProvider('invalid')]
    public function testRefusesOptionsOutOfRange(int $quietZone, string $dark, string $light, string $message, int $size = 0): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SvgWriter($quietZone, $dark, $light, $size);
    }
}
