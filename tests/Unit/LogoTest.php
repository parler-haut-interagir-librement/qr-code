<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Unit;

use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Logo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Logo::class)]
#[CoversClass(InvalidOption::class)]
final class LogoTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function images(): iterable
    {
        yield 'PNG' => [(string) file_get_contents(__DIR__.'/../Fixtures/logo.png'), 'image/png'];
        yield 'JPEG' => [(string) file_get_contents(__DIR__.'/../Fixtures/logo.jpg'), 'image/jpeg'];
        yield 'WebP' => ['RIFF'."\x10\0\0\0".'WEBPVP8 ', 'image/webp'];
        yield 'SVG' => ['<svg viewBox="0 0 1 1"/>', 'image/svg+xml'];
        yield 'SVG with a declaration, a comment and a doctype' => ["\xEF\xBB\xBF<?xml version=\"1.0\"?>\n<!-- a\nlogo -->\n<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"x\">\n<svg\nxmlns=\"http://www.w3.org/2000/svg\"/>", 'image/svg+xml'];
    }

    #[DataProvider('images')]
    public function testRecognisesTheImageByItsFirstBytes(string $bytes, string $type): void
    {
        $logo = Logo::fromBytes($bytes);

        self::assertSame($type, $logo->mediaType);
        self::assertSame($bytes, $logo->bytes);
        self::assertSame('data:'.$type.';base64,'.base64_encode($bytes), $logo->dataUri());
    }

    public function testReadsAFile(): void
    {
        self::assertSame('image/svg+xml', Logo::fromFile(__DIR__.'/../Fixtures/logo.svg')->mediaType);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => ['', 'The logo must be a PNG, JPEG, WebP or SVG image; got other bytes.'];
        yield 'HTML' => ['<html><svg/></html>', 'got other bytes'];
        yield 'a RIFF that is not WebP' => ['RIFF'."\x10\0\0\0".'WAVEfmt ', 'got other bytes'];
        yield 'an element named svgx' => ['<svgx/>', 'got other bytes'];
        yield 'too large' => ['<svg>'.str_repeat(' ', Logo::MAX_BYTES), 'The logo must be at most 1 MiB; got 1048581 bytes.'];
    }

    #[DataProvider('refused')]
    public function testRefusesWhatIsNotALogo(string $bytes, string $message): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        Logo::fromBytes($bytes);
    }

    public function testAcceptsTheLargestLogo(): void
    {
        self::assertSame('image/svg+xml', Logo::fromBytes('<svg>'.str_repeat(' ', Logo::MAX_BYTES - 5))->mediaType);
    }

    public function testReadsTheLargestFile(): void
    {
        $path = sys_get_temp_dir().'/phil-qr-code-logo-'.bin2hex(random_bytes(4)).'.svg';
        file_put_contents($path, '<svg>'.str_repeat(' ', Logo::MAX_BYTES - 5));

        try {
            self::assertSame(Logo::MAX_BYTES, \strlen(Logo::fromFile($path)->bytes));
        } finally {
            unlink($path);
        }
    }

    public function testRefusesAMissingFile(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('The logo must be a readable file; got "'.__DIR__.'/missing.png".');

        Logo::fromFile(__DIR__.'/missing.png');
    }

    public function testRefusesADirectory(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('a readable file');

        Logo::fromFile(__DIR__);
    }

    public function testRefusesALargeFileBeforeReadingIt(): void
    {
        $path = sys_get_temp_dir().'/phil-qr-code-logo-'.bin2hex(random_bytes(4)).'.svg';
        file_put_contents($path, '<svg>'.str_repeat(' ', Logo::MAX_BYTES));

        try {
            $this->expectException(InvalidOption::class);
            $this->expectExceptionMessageIsOrContains('The file of the logo must be at most 1 MiB; got 1048581 bytes.');

            Logo::fromFile($path);
        } finally {
            unlink($path);
        }
    }
}
