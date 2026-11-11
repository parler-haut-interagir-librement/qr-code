<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Benchmark;

use Phil\QrCode\Content\Url;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\PngWriter;
use Phil\QrCode\QrCode;
use Phil\QrCode\SvgWriter;

/**
 * The encoding of the codes pages and documents draw: an address of a ticket, a mixed text that needs the shortest
 * segments, a code with the zone of a logo, a large code; then the two writers.
 *
 * @Groups({"qr-code"})
 */
final readonly class EncodeBench
{
    private QrCode $code;

    public function __construct()
    {
        $this->code = QrCode::encode('https://example.com/tickets/42?seat=A12', ErrorCorrection::Quartile);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchEncodeAnAddress(): void
    {
        QrCode::encode(new Url('https://example.com/tickets/42?seat=A12'), ErrorCorrection::Quartile);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchEncodeAMixedText(): void
    {
        QrCode::encode('Billet 点茗 HTTPS://EXAMPLE.COM/T/'.str_repeat('1234567890', 4).' rangée Ü');
    }

    /**
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchEncodeWithALogoZone(): void
    {
        QrCode::encode('https://example.com/tickets/42', logoZone: 0.25);
    }

    /**
     * @Revs(2)
     *
     * @Iterations(3)
     */
    public function benchEncodeVersion40(): void
    {
        QrCode::encode(str_repeat('a', 2900), ErrorCorrection::Low);
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     */
    public function benchWriteSvg(): void
    {
        new SvgWriter()->write($this->code);
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function benchWritePng(): void
    {
        new PngWriter()->write($this->code);
    }
}
