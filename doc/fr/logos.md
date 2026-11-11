<!-- phil-dev source-sha256=505886fd58b4f77f72918025d8898bca0d5c7624783ab6c9d8fe0510aa7e3737 -->
# Logos

Un logo se place dans la zone qu'un code garde en son centre, et la correction d'erreurs compense les modules qu'il
cache.

```php
$code = QrCode::encode('https://example.com/tickets/42', logoZone: 0.25);
$logo = Logo::fromFile(__DIR__.'/logo.svg');

$svg = new SvgWriter()->write($code, 'Ticket 42', $logo);
$png = new PngWriter(8)->write($code, Logo::fromFile(__DIR__.'/logo.png'));
```

## La zone

`$logoZone` relève le niveau à `High` et choisit la plus petite version dont chaque bloc peut corriger la zone en
gardant la moitié de sa capacité en réserve : voir [Encodage](encoding.md#la-zone-dun-logo). Une zone de 0,2 à 0,25 du
côté convient à la plupart des logos ; 0,3 est la plus grande.

## L'image

`Logo::fromBytes()` et `Logo::fromFile()` lisent une image PNG, JPEG, WebP ou SVG d'au plus 1 Mio, reconnue par ses
premiers octets, jamais par un nom de fichier ou un type de média fourni par quelqu'un. `mediaType` dit lequel, et
`dataUri()` la donne sous forme d'URI `data:`.

| Moteur | Formats | Comment |
|---|---|---|
| `SvgWriter` | les quatre | un `<image>` avec une URI `data:`, à un demi-module à l'intérieur de la zone, `preserveAspectRatio="xMidYMid meet"` |
| `PngWriter` | PNG, JPEG, WebP | dessiné avec GD, ajusté et centré, la transparence fondue sur la couleur claire |

Gardez une marge claire dans le logo lui-même et un fort contraste autour : les modules propres à la zone sont clairs.
