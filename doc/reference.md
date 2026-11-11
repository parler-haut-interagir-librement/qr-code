# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `QrCode` | A symbol: `encode()`, `fromSegments()`, `size()`, `isDark()`, `rows()`, and how it was built. |
| `Segment` | A run of the data in one mode, or an ECI header. |
| `Mode` | The numeric, alphanumeric, byte and kanji modes, and the ECI header. |
| `ErrorCorrection` | The four levels of error correction, `L`, `M`, `Q` and `H`. |
| `Zone` | The square a code keeps at its centre for a logo. |
| `Logo` | A PNG, JPEG, WebP or SVG image to draw in the zone. |
| `SvgWriter` | Draws a `QrCode` as an SVG image. |
| `PngWriter` | Draws a `QrCode` as a PNG image. |
| `Contract\Content` | What a typed content gives: its payload and its label. |
| `Content\Url`, `Email`, `Phone`, `Sms`, `Geo` | Addresses to open, write to, call, text, or a place. |
| `Content\Wifi`, `WifiSecurity` | A network to join, and its protection. |
| `Content\MeCard`, `VCard`, `VCardVersion`, `PostalAddress` | A contact, in the compact MeCard form or as a vCard. |
| `Content\Event` | An event to add to a calendar. |
| `Exception\QrCodeException` | Implemented by every exception of the package. |
| `Exception\DataTooLong` | The data does not fit in version 40 at the level asked. |
| `Exception\InvalidOption` | A version, mask, zone, segment, quiet zone, colour, scale, size or logo out of its range. |
| `Exception\InvalidContent` | A field of a typed content that cannot be written. |
