# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Draw a code another way — PDF, terminal | Read `QrCode::rows()` or `isDark()`, and `zone`, and draw the modules; `SvgWriter` and `PngWriter` are two such drawings |
| Write another kind of content | Implement `Contract\Content`: its `payload()`, escaped as its format requires, and a `label()` without secrets |
| Choose the segments | Build `Segment`s and pass them to `QrCode::fromSegments()` |
| Keep a code the same size whatever its text | Pass `$minVersion` to `QrCode::encode()` |
| Keep a code the same across versions of the package | Pass `$mask` to `QrCode::encode()` |
| Leave room for a logo | Pass `$logoZone` to `QrCode::encode()`, and a `Logo` to the writer |

The classes under `Internal` are not extension points.
