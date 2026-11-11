# SVG

`SvgWriter` draws a `QrCode` as a standalone SVG image.

```php
new SvgWriter(int $quietZone = 4, string $dark = '#000000', string $light = '#ffffff', int $size = 0)
    ->write(QrCode $code, string $title = '', ?Logo $logo = null): string
```

The image is a light square of the code's size plus the quiet zone on each side, and one path of dark squares, a
run of modules of a row for each sub-path, in units of one module: `viewBox="0 0 29 29"` for a version 1 code with
the standard quiet zone. `shape-rendering="crispEdges"` keeps the edges sharp at any size.

| Option | Range |
|---|---|
| `$quietZone` | 0 to 16 modules; readers need 4 around a code printed on a busy background |
| `$dark`, `$light` | `#rrggbb`; keep a strong contrast, dark on light |
| `$size` | 0 to 4096: the `width` and `height` written, in pixels; 0 writes none, and the image takes the size its container gives it |

`InvalidOption` refuses any other value.

The zone of a code encoded with `$logoZone` is left light; a `Logo` given to `write()` is drawn over it, half a module
inside its edges — see [Logos](logos.md). A logo for a code without a zone is refused.

## Accessibility

The image carries `role="img"` and a `<title>`: the `$title` given, or the code's own text. Screen readers announce
it; a page that shows the address beside the code may give a shorter title, such as "QR code of the ticket". The text
of a Wi-Fi code holds its password: give such a code the `label()` of its content as title.

## Safety

The only text written is the title, escaped for XML. A logo is embedded as a `data:` URI in an `<image>` element,
where an SVG logo runs no script and loads nothing. The image holds no script, link, style sheet nor external
reference, so it is safe to write inline in a page and to keep in a database.
