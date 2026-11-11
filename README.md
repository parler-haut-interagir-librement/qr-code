# phil/qr-code

**QR codes drawn as SVG or PNG by PHP alone, with no service and no image library.**

phil/qr-code encodes a text as a QR code (model 2, ISO/IEC 18004) and draws it as an SVG or a PNG image. The text is
split into the shortest sequence of numeric, alphanumeric, byte and kanji segments, announced as UTF-8 by an ECI
header when its bytes need it, put in the smallest of the 40 versions that holds it at the level of error correction
asked, protected by Reed-Solomon codes, and laid out under the mask of the lowest penalty. A code can keep a zone at
its centre for a logo, the version growing until every block can correct it. Typed contents write addresses,
contacts, Wi-Fi networks, events, places, messages and numbers in the forms phone readers act on, escaped as their
formats require.

It is for any PHP code that prints or shows a QR code: a ticket, a receipt, a gift card, a poster, a page block, a
printed document. Nothing is sent anywhere: the text of a code may be private, and it never leaves the server.

It is a plain PHP library built on `ext-mbstring`, `ext-sodium` and `ext-zlib`; `ext-gd` only draws
a logo over a PNG. Its output is deterministic: the same text and options give the same bytes.

## Status

| Part | State |
|---|---|
| Numeric, alphanumeric, byte and kanji segments; the shortest sequence found exactly for each range of versions | Implemented and tested |
| The ECI header of UTF-8; segments built by the caller | Implemented and tested |
| Versions 1 to 40, levels L, M, Q and H, a smallest version for codes printed at one size | Implemented and tested |
| Reed-Solomon codes, block interleaving, format and version information | Implemented and tested |
| The eight masks and the four penalty rules, or a mask chosen by the caller | Implemented and tested |
| The zone of a logo, with the level raised and the version chosen for each block to correct it | Implemented and tested |
| `SvgWriter`: one path, quiet zone, colours, size, a title for screen readers, a logo | Implemented and tested |
| `PngWriter`: one bit per pixel, written by the library; a logo drawn with GD | Implemented and tested |
| Typed contents: URL, mailto, tel, SMS, geo, Wi-Fi, MeCard, vCard 3.0 and 4.0, iCalendar event | Implemented, tested and fuzzed |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Encoding](doc/encoding.md) | Segments, ECI, versions, levels, the zone of a logo, masks, limits. |
| [Typed contents](doc/contents.md) | Each content, its payload, its escapes and its label. |
| [SVG](doc/svg.md) | The image `SvgWriter` draws, its options and its accessibility. |
| [PNG](doc/png.md) | The image `PngWriter` writes, and its options. |
| [Logos](doc/logos.md) | The zone, the images accepted, how each writer draws them. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests, its reader, its fuzzing, its benchmarks and its mutation bar. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/qr-code
```

```php
use Phil\QrCode\Content\Wifi;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\PngWriter;
use Phil\QrCode\QrCode;
use Phil\QrCode\SvgWriter;

$code = QrCode::encode('https://example.com/tickets/42', ErrorCorrection::Quartile);
$svg = new SvgWriter()->write($code, 'Ticket 42');   // <svg viewBox="0 0 37 37" role="img">…
$png = new PngWriter(8)->write($code);               // the bytes of a PNG file

$wifi = new Wifi('Gnome Café', 'tea-and-dice');
$svg = new SvgWriter()->write(QrCode::encode($wifi), $wifi->label());
```

## Requirements

PHP 8.5 or later, with `ext-mbstring`, `ext-sodium` and `ext-zlib`; `ext-gd` to draw a logo over a PNG.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
