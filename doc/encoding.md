# Encoding

`QrCode::encode()` turns a text, or the payload of a [typed content](contents.md), into the modules of a symbol.

```php
QrCode::encode(
    string|Content $data,
    ErrorCorrection $level = ErrorCorrection::Medium,
    int $minVersion = 1,
    ?int $mask = null,
    float $logoZone = 0.0,
    bool $eci = true,
    bool $kanji = true,
): QrCode
```

## Segments

A text is written as a sequence of segments, each in one mode:

| Mode | Characters | Bits |
|---|---|---|
| `Mode::Numeric` | `0`–`9` | 10 for three digits, 7 for two, 4 for one |
| `Mode::Alphanumeric` | `0`–`9`, `A`–`Z`, space and `$%*+-./:` | 11 for two characters, 6 for one |
| `Mode::Byte` | any byte | 8 for each byte |
| `Mode::Kanji` | the double-byte characters of Shift JIS (JIS X 0208) | 13 for each character |

Each segment costs its mode indicator and its character count, whose width grows at versions 10 and 27. The encoder
finds the shortest sequence by dynamic programming over the characters, with the exact bits of each choice — the
unfinished group of digits or of alphanumeric characters, the header of each new segment, the ECI header —, for the
range of versions it tries: `HTTPS://EXAMPLE.COM/` followed by forty digits is one alphanumeric and one numeric
segment, and an address in lower case followed by two digits stays one byte segment.

`Segment` builds segments by hand — `numeric()`, `alphanumeric()`, `bytes()`, `kanji()`, `eci()` — and
`QrCode::fromSegments()` encodes them in their order:

```php
QrCode::fromSegments([Segment::eci(Segment::UTF8), Segment::bytes('Prix : '), Segment::numeric('1250'), Segment::kanji('円')]);
```

## UTF-8 and the ECI header

A reader takes bytes as ISO-8859-1 unless an ECI header names their character set. When a byte segment holds a
byte beyond ASCII, the code starts with the ECI header of UTF-8, assignment 26, in twelve bits; `eci: false` leaves it
out for the readers that mishandle it. A text that is not valid UTF-8 is written as bytes, without ECI and without
kanji.

`kanji: false` writes the characters of Shift JIS as UTF-8 bytes too, for the rare reader without the kanji mode.

## Versions and levels

A symbol has 21 × 21 modules at version 1 and 177 × 177 at version 40, four more on a side for each version. The
smallest version from `$minVersion` that holds the segments at the level is used; `$minVersion` keeps codes printed in
one place at one size.

| Level | Codewords that may be lost |
|---|---|
| `ErrorCorrection::Low` | about 7 % |
| `ErrorCorrection::Medium` | about 15 % |
| `ErrorCorrection::Quartile` | about 25 % |
| `ErrorCorrection::High` | about 30 %, for a code printed small or under a logo |

Version 40 holds at most 7 089 digits, 4 296 alphanumeric characters or 2 953 bytes at level L; `DataTooLong` refuses
longer texts, and refuses any text longer than 7 089 bytes before computing anything.

## The zone of a logo

`$logoZone`, from 0 to 0.3, is the share of the side kept at the centre for a logo. Above 0:

- the level is raised to `High`;
- the zone is a centred square of an odd number of modules, so it sits on the centre module, clear of the finders,
  the timing patterns and the format and version information;
- the version is the smallest from `$minVersion` whose blocks each lose at most a quarter of their error correction
  codewords under the zone — half of what a block can correct, the other half left for wear. A large logo makes a
  larger code.

`QrCode::$zone` is the `Zone` kept, null without a logo: its `x`, `y` and `side` in modules, and `contains($x, $y)`.
`rows()` keeps the true modules; the writers leave the zone light and draw the logo over it — see [Logos](logos.md).

## Masks

Each of the eight masks inverts the data modules where its condition holds; the one of the lowest penalty — runs of
one colour, 2 × 2 squares, patterns that look like a finder, a share of dark modules far from half — is kept. `$mask`
forces one, from 0 to 7, for tests or for codes that must not change when the rules are refined.

## Reading the symbol

`size()` is the number of modules on a side, `isDark($x, $y)` reads one module from the top left corner, and `rows()`
gives each row as a string of `1` and `0`. `version`, `level`, `segments`, `mask`, `zone` and `data` say how it was
built.

## Cost

Encoding is linear in the text, and the eight masks are scored on the whole symbol: a short address takes about
10 ms, a version 40 code about a third of a second (see `tests/Benchmark/EncodeBench.php`). A code is drawn once and
kept, not drawn for each reader.
