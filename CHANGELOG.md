# Changelog

All notable changes to `phil/qr-code` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## NEXT - YYYY-MM-DD

### Added

- `QrCode::encode()`: a text or a typed content as a QR code of model 2, in the shortest sequence of numeric,
  alphanumeric, byte and kanji segments — found by dynamic programming with the exact bits of each choice, for each
  range of versions —, behind the ECI header of UTF-8 when its bytes go beyond ASCII (`eci`), with the kanji of
  Shift JIS (`kanji`), in the smallest of the 40 versions from a smallest one, at the levels L, M, Q and H, with
  Reed-Solomon codes, block interleaving, format and version information, and the mask of the lowest penalty or the
  one asked.
- `Segment` — `numeric()`, `alphanumeric()`, `bytes()`, `kanji()`, `eci()` — and `QrCode::fromSegments()` for segments
  built by the caller.
- `logoZone`: a centred `Zone` kept for a logo, the level raised to H, and the smallest version whose blocks each lose
  at most a quarter of their error correction codewords under it.
- `size()`, `isDark()` and `rows()` to read the modules; `version`, `level`, `segments`, `mask`, `zone` and `data`.
- `SvgWriter`: one path of crisp modules, a quiet zone of 0 to 16 modules, `#rrggbb` colours, an optional size,
  `role="img"` and an escaped `<title>`, the zone left light and a logo drawn over it as a `data:` URI.
- `PngWriter`: a PNG written by the library, one bit per pixel with a palette, rows compressed by zlib as they are
  made, a scale of 1 to 32 pixels a module; a PNG, JPEG or WebP logo drawn over the zone with GD, in 8-bit RGB.
- `Logo`: a PNG, JPEG, WebP or SVG image of at most 1 MiB, recognised by its first bytes.
- Typed contents under `Contract\Content`, each with its payload and a label without secrets: `Url`, `Email`
  (mailto), `Phone` (tel), `Sms`, `Geo`, `Wifi` with `WifiSecurity`, `MeCard`, `VCard` 3.0 and 4.0 with
  `PostalAddress`, and `Event` (iCalendar VEVENT), with the escapes, percent-encoding and folded lines of their
  formats.
- `DataTooLong`, `InvalidOption` and `InvalidContent`, under the `QrCodeException` interface; texts beyond version 40
  refused before any computation.
- Tests against the standard's examples, golden symbols read back by ZXing, a reader of the tests written apart from
  the encoder, the segmentation against every assignment of short texts, every mask and penalty rule; fuzz targets
  for the typed contents and the encoder; benchmarks of the encoding and the writers.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
