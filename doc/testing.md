# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network. It checks the capacity tables against the standard's
totals, the Reed-Solomon codewords of the standard's example `HELLO WORLD` at 1-Q, the kanji of the standard's
example `点茗`, the BCH codes of the format and version information, each mask and each penalty rule, and whole
symbols against golden files in `tests/Unit/Fixture`.

The golden symbols, and a sweep of every version at every level, were read back by an independent decoder (ZXing)
when they were written, without the ECI header: a change that alters a golden file must be checked the same way
before the file is replaced.

`tests/Support/Decoder` is a reader written apart from the encoder, which shares only the block table of the
standard: it reads the format information, marks the function patterns from the standard's table of alignment
centres, removes the mask, splits the blocks, checks each by its syndromes — or solves the codewords under a logo
from them —, and parses the segments. It reads the golden symbols, and the tests read back through it texts of every
mode, codes with a logo zone cleared, and PNG images sampled pixel by pixel.

The segmentation is checked against every assignment of a mode to each character of short texts. Each typed content
is checked to the byte, and its fields read back by `tests/Support/Fields`.

## Fuzzing and benchmarks

`composer fuzz` runs two targets: `contents`, the typed contents on fields nobody wrote, which either refuse them or
write a UTF-8 payload whose fields read back; and `encode`, any bytes encoded and read back by the reader of the tests.
`tests/Benchmark/EncodeBench.php` measures the encoding of an address, of a mixed text, of a code with a logo zone, of
a version 40 code, and both writers.

In the monorepo, Infection holds the package at 100 % of covered mutants.

## Testing code that draws codes

A QR code is deterministic: compare `rows()`, the SVG or the PNG with a value of your own, or check `version` and
`segments`.
