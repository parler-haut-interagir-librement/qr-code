# PNG

`PngWriter` draws a `QrCode` as a PNG file, written by the library itself.

```php
new PngWriter(int $scale = 8, int $quietZone = 4, string $dark = '#000000', string $light = '#ffffff')
    ->write(QrCode $code, ?Logo $logo = null): string
```

| Option | Range |
|---|---|
| `$scale` | 1 to 32 pixels on a side of a module |
| `$quietZone` | 0 to 16 modules |
| `$dark`, `$light` | `#rrggbb` |

The image is a square of `(size + 2 × quiet zone) × scale` pixels. Without a logo it is written in two colours on
one bit per pixel, with a palette of the light and the dark colour; with one, in 8-bit RGB. Rows are compressed by
zlib as they are made, so a large code never holds its pixels in memory. The same code and options give the same
bytes.

The zone of a code encoded with `$logoZone` is left light. A PNG, JPEG or WebP `Logo` is drawn over it with GD, fitted
and centred half a module inside its edges, its transparency blended on the light colour. An SVG logo, a logo GD
cannot read, a missing GD extension and a code without a zone are refused with `InvalidOption`: draw an SVG logo with
`SvgWriter`.

A PNG carries no text for screen readers: the page that shows it gives the `<img>` its `alt`.
