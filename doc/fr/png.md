<!-- phil-dev source-sha256=8424a7ba797927fb642e07bec90d7789f9ce244e3cdf683ef91a5362ec6a1e91 -->
# PNG

`PngWriter` dessine un `QrCode` sous forme de fichier PNG, écrit par la bibliothèque elle-même.

```php
new PngWriter(int $scale = 8, int $quietZone = 4, string $dark = '#000000', string $light = '#ffffff')
    ->write(QrCode $code, ?Logo $logo = null): string
```

| Option | Plage |
|---|---|
| `$scale` | De 1 à 32 pixels par côté de module |
| `$quietZone` | De 0 à 16 modules |
| `$dark`, `$light` | `#rrggbb` |

L'image est un carré de `(size + 2 × quiet zone) × scale` pixels. Sans logo, elle est écrite en deux couleurs sur un
bit par pixel, avec une palette de la couleur claire et de la couleur foncée ; avec un logo, en RVB sur 8 bits. Les
rangées sont compressées par zlib à mesure qu'elles sont produites : un grand code ne garde ainsi jamais ses pixels en
mémoire. Le même code et les mêmes options donnent les mêmes octets.

La zone d'un code encodé avec `$logoZone` est laissée claire. Un `Logo` PNG, JPEG ou WebP est dessiné par-dessus avec
GD, ajusté et centré à un demi-module de ses bords, sa transparence fondue sur la couleur claire. Un logo SVG, un logo
que GD ne sait pas lire, une extension GD absente et un code sans zone sont refusés avec `InvalidOption` : dessinez un
logo SVG avec `SvgWriter`.

Un PNG ne porte aucun texte pour les lecteurs d'écran : la page qui l'affiche donne son `alt` à l'`<img>`.
