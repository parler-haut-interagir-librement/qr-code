# Installation

```sh
composer require phil/qr-code
```

The package needs PHP 8.5 or later with `ext-ctype`, `ext-filter`, `ext-mbstring`, `ext-sodium` and `ext-zlib`.
`ext-gd` is suggested: `PngWriter` needs it only to draw a logo over a PNG. The package has no other dependency and calls no service.
