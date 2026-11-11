# Logos

A logo sits in the zone a code keeps at its centre, and the error correction makes up for the modules it hides.

```php
$code = QrCode::encode('https://example.com/tickets/42', logoZone: 0.25);
$logo = Logo::fromFile(__DIR__.'/logo.svg');

$svg = new SvgWriter()->write($code, 'Ticket 42', $logo);
$png = new PngWriter(8)->write($code, Logo::fromFile(__DIR__.'/logo.png'));
```

## The zone

`$logoZone` raises the level to `High` and chooses the smallest version whose blocks can each correct the zone with
half of their capacity to spare: see [Encoding](encoding.md#the-zone-of-a-logo). A zone of 0.2 to 0.25 of the side
suits most logos; 0.3 is the largest.

## The image

`Logo::fromBytes()` and `Logo::fromFile()` read a PNG, JPEG, WebP or SVG image of at most 1 MiB, recognised by its
first bytes, never by a file name or a media type someone gave. `mediaType` says which, and `dataUri()` gives it as a
`data:` URI.

| Writer | Formats | How |
|---|---|---|
| `SvgWriter` | all four | an `<image>` with a `data:` URI, half a module inside the zone, `preserveAspectRatio="xMidYMid meet"` |
| `PngWriter` | PNG, JPEG, WebP | drawn with GD, fitted and centred, transparency blended on the light colour |

Keep a light margin inside the logo itself and a strong contrast around it: the zone's own modules are light.
