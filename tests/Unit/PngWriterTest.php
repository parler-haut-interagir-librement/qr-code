<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Unit;

use GdImage;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Logo;
use Phil\QrCode\PngWriter;
use Phil\QrCode\QrCode;
use Phil\QrCode\Tests\Support\Decoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(PngWriter::class)]
#[CoversClass(InvalidOption::class)]
#[RequiresPhpExtension('gd')]
final class PngWriterTest extends TestCase
{
    public function testWritesTwoColoursOnOneBitPerPixel(): void
    {
        $code = QrCode::encode('HELLO WORLD', ErrorCorrection::Quartile);
        $png = new PngWriter(3, 2)->write($code);

        self::assertStringStartsWith("\x89PNG\r\n\x1A\n\0\0\0\x0DIHDR\0\0\0\x4B\0\0\0\x4B\x01\x03\0\0\0", $png);
        self::assertStringContainsString("\0\0\0\x06PLTE\xFF\xFF\xFF\0\0\0", $png);
        self::assertStringEndsWith("\0\0\0\0IEND\xAE\x42\x60\x82", $png);
        self::assertSame($code->rows(), $this->modules($png, 3, 2, $code->size()));
        self::assertSame($png, new PngWriter(3, 2)->write($code), 'the same code gives the same bytes');
        // 75 rows of a filter byte and 10 bytes of 75 pixels, no more.
        self::assertSame(75 * (1 + 10), \strlen((string) gzuncompress($this->chunks($png)['IDAT'])));
        $logo = QrCode::encode('https://example.com/t/42', logoZone: 0.2);
        $side = $logo->size() + 8;
        self::assertSame($side * (1 + 3 * $side), \strlen((string) gzuncompress($this->chunks(new PngWriter(1, 4)->write($logo, Logo::fromFile(__DIR__.'/../Fixtures/logo.png')))['IDAT'])));
    }

    public function testDrawsTheQuietZoneAndTheColours(): void
    {
        $code = QrCode::encode('A');
        $image = $this->image(new PngWriter(2, 4, '#123456', '#abcdef')->write($code));

        self::assertSame(58, imagesx($image));
        self::assertSame(58, imagesy($image));
        self::assertSame(0xABCDEF, $this->rgb($image, 7, 7));
        self::assertSame(0x123456, $this->rgb($image, 8, 8));
        self::assertSame(0x123456, $this->rgb($image, 9, 9));
        self::assertSame(0xABCDEF, $this->rgb($image, 57, 57));
    }

    public function testPadsEachRowToAByte(): void
    {
        // 21 modules and no quiet zone: 21 pixels a row, three bytes with three bits of padding.
        $code = QrCode::encode('A');

        self::assertSame($code->rows(), $this->modules(new PngWriter(1, 0)->write($code), 1, 0, 21));
    }

    public function testLeavesTheZoneOfALogoLightWithoutALogo(): void
    {
        $code = QrCode::encode('https://example.com/t/42', logoZone: 0.25);
        $zone = $code->zone;
        self::assertNotNull($zone);
        $rows = $this->modules(new PngWriter(2, 1)->write($code), 2, 1, $code->size());

        for ($y = 0; $y < $code->size(); ++$y) {
            for ($x = 0; $x < $code->size(); ++$x) {
                self::assertSame($zone->contains($x, $y) ? '0' : $code->rows()[$y][$x], $rows[$y][$x]);
            }
        }
    }

    public function testDrawsALogoInTheZone(): void
    {
        $code = QrCode::encode('https://example.com/t/42', logoZone: 0.25);
        $zone = $code->zone;
        self::assertNotNull($zone);
        $png = new PngWriter(4, 4, '#000000', '#ffffff')->write($code, Logo::fromFile(__DIR__.'/../Fixtures/logo.png'));
        $image = $this->image($png);
        $centre = (int) ((4 + $code->size() / 2) * 4);

        self::assertStringStartsWith("\x89PNG\r\n\x1A\n\0\0\0\x0DIHDR", $png);
        self::assertSame("\x08\x02", substr($png, 24, 2), '8-bit RGB');
        self::assertStringNotContainsString('PLTE', $png);
        self::assertSame(0xC81E1E, $this->rgb($image, $centre + 4, $centre));
        // The transparent part of the logo shows the light colour.
        $left = (4 + $zone->x) * 4 + 2;
        self::assertSame(0xFFFFFF, $this->rgb($image, $left + 1, $centre));

        $rows = $this->modules($png, 4, 4, $code->size());

        for ($y = 0; $y < $code->size(); ++$y) {
            for ($x = 0; $x < $code->size(); ++$x) {
                if (!$zone->contains($x, $y)) {
                    self::assertSame($code->rows()[$y][$x], $rows[$y][$x]);
                }
            }
        }
    }

    public function testDrawsWithItsDefaults(): void
    {
        $image = self::image(new PngWriter()->write(QrCode::encode('A')));

        self::assertSame((21 + 8) * 8, imagesx($image));
        self::assertSame(0xFFFFFF, $this->rgb($image, 31, 31));
        self::assertSame(0x000000, $this->rgb($image, 32, 32));
    }

    public function testFitsATallLogoInTheZoneKeepingItsProportions(): void
    {
        $tall = imagecreatetruecolor(20, 40);
        self::assertInstanceOf(GdImage::class, $tall);
        imagealphablending($tall, false);
        imagesavealpha($tall, true);
        imagefilledrectangle($tall, 0, 0, 19, 39, (int) imagecolorallocate($tall, 0, 0, 255));
        imagefilledrectangle($tall, 0, 0, 19, 9, (int) imagecolorallocatealpha($tall, 0, 0, 0, 127));
        ob_start();
        imagepng($tall);
        $logo = Logo::fromBytes((string) ob_get_clean());

        $code = QrCode::encode('https://example.com/t/42', logoZone: 0.3);
        $zone = $code->zone;
        self::assertNotNull($zone);
        $image = self::image(new PngWriter(4, 2, '#000000', '#fefdfc')->write($code, $logo));

        // The zone is side × 4 pixels; the logo's height spans it less half a module on each side, its width half that.
        $side = $zone->side * 4;
        $h = $side - 4;
        $w = intdiv(20 * $h, 40);
        $left = (2 + $zone->x) * 4 + intdiv($side - $w, 2);
        $top = (2 + $zone->y) * 4 + 2;
        $middle = $left + intdiv($w, 2);

        self::assertSame(0x0000FF, $this->rgb($image, $left + 2, $top + intdiv($h, 2)));
        self::assertSame(0x0000FF, $this->rgb($image, $left + $w - 3, $top + intdiv($h, 2)));
        self::assertSame(0xFEFDFC, $this->rgb($image, $left - 2, $top + intdiv($h, 2)));
        self::assertSame(0xFEFDFC, $this->rgb($image, $left + $w + 1, $top + intdiv($h, 2)));
        self::assertSame(0x0000FF, $this->rgb($image, $middle, $top + $h - 1));
        self::assertSame(0xFEFDFC, $this->rgb($image, $middle, $top + $h));
        // The transparent quarter at the top shows the light colour.
        self::assertSame(0xFEFDFC, $this->rgb($image, $middle, $top + 2));
        self::assertSame(0xFEFDFC, $this->rgb($image, (2 + $zone->x) * 4, (2 + $zone->y) * 4));
        self::assertSame(0xFEFDFC, $this->rgb($image, (2 + $zone->x) * 4 + $side - 1, (2 + $zone->y) * 4 + $side - 1));
    }

    public function testDrawsAJpegLogoReadByTheReaderOfTheTests(): void
    {
        $code = QrCode::encode('https://example.com/t/42', logoZone: 0.2);
        $zone = $code->zone;
        self::assertNotNull($zone);
        $rows = $this->modules(new PngWriter(5, 0)->write($code, Logo::fromFile(__DIR__.'/../Fixtures/logo.jpg')), 5, 0, $code->size());
        $lost = [];

        for ($y = $zone->y; $y < $zone->y + $zone->side; ++$y) {
            for ($x = $zone->x; $x < $zone->x + $zone->side; ++$x) {
                $lost[] = $y * $code->size() + $x;
            }
        }

        self::assertSame([['byte', 'https://example.com/t/42']], Decoder::segments($rows, $lost));
    }

    /**
     * @return iterable<string, array{int, int, string, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'a scale of 0' => [0, 4, '#000000', '#ffffff', 'The scale must be from 1 to 32 pixels a module; got 0.'];
        yield 'a scale of 33' => [33, 4, '#000000', '#ffffff', 'got 33'];
        yield 'a negative quiet zone' => [8, -1, '#000000', '#ffffff', 'The quiet zone must be from 0 to 16 modules; got -1.'];
        yield 'a quiet zone of 17' => [8, 17, '#000000', '#ffffff', 'got 17'];
        yield 'a dark colour by name' => [8, 4, 'black', '#ffffff', 'The dark colour must be a colour written #rrggbb; got "black".'];
        yield 'a short light colour' => [8, 4, '#000000', '#fff', 'The light colour must be a colour written #rrggbb; got "#fff".'];
        yield 'a colour and a line' => [8, 4, "#000000\n", '#ffffff', 'The dark colour'];
    }

    #[DataProvider('invalid')]
    public function testRefusesOptionsOutOfRange(int $scale, int $quietZone, string $dark, string $light, string $message): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        new PngWriter($scale, $quietZone, $dark, $light);
    }

    public function testAcceptsTheBoundsOfTheOptions(): void
    {
        self::assertSame(53 * 32, imagesx($this->image(new PngWriter(32, 16)->write(QrCode::encode('A')))));
        self::assertSame(21, imagesx($this->image(new PngWriter(1, 0)->write(QrCode::encode('A')))));
    }

    public function testRefusesALogoWithoutAZone(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('The code of a logo must be encoded with a zone for it; got a code without one.');

        new PngWriter()->write(QrCode::encode('A'), Logo::fromFile(__DIR__.'/../Fixtures/logo.png'));
    }

    public function testRefusesAnSvgLogo(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('The logo of a PNG must be a PNG, JPEG or WebP image; got an SVG; draw it with SvgWriter.');

        new PngWriter()->write(QrCode::encode('A', logoZone: 0.2), Logo::fromFile(__DIR__.'/../Fixtures/logo.svg'));
    }

    public function testRefusesALogoGdCannotRead(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('The logo of a PNG must be an image GD can read; got unreadable bytes.');

        new PngWriter()->write(QrCode::encode('A', logoZone: 0.2), Logo::fromBytes("\x89PNG\r\n\x1A\nbroken"));
    }

    /**
     * The chunks of a PNG file, by type.
     *
     * @return array<string, string>
     */
    private function chunks(string $png): array
    {
        $chunks = [];

        for ($at = 8; $at < \strlen($png); $at += 12 + $length) {
            $length = \ord($png[$at]) << 24 | \ord($png[$at + 1]) << 16 | \ord($png[$at + 2]) << 8 | \ord($png[$at + 3]);
            $chunks[substr($png, $at + 4, 4)] = substr($png, $at + 8, $length);
        }

        return $chunks;
    }

    /**
     * The modules read at the centre of each one.
     *
     * @return list<string>
     */
    private function modules(string $png, int $scale, int $quietZone, int $size): array
    {
        $image = $this->image($png);
        $rows = [];

        for ($y = 0; $y < $size; ++$y) {
            $row = '';

            for ($x = 0; $x < $size; ++$x) {
                $c = intdiv($scale, 2);
                $row .= $this->rgb($image, ($x + $quietZone) * $scale + $c, ($y + $quietZone) * $scale + $c) < 0x808080 ? '1' : '0';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function image(string $png): GdImage
    {
        $image = imagecreatefromstring($png);
        self::assertInstanceOf(GdImage::class, $image);
        imagepalettetotruecolor($image);

        return $image;
    }

    private function rgb(GdImage $image, int $x, int $y): int
    {
        return imagecolorat($image, $x, $y) & 0xFFFFFF;
    }
}
