<!-- phil-dev source-sha256=f983097a9c71887f370277d54ac379d20e439eb14a35abf04cac8c9474229463 -->
# Encodage

`QrCode::encode()` transforme un texte, ou la charge utile d'un [contenu typé](contents.md), en modules d'un symbole.

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

Un texte s'écrit comme une suite de segments, chacun dans un mode :

| Mode | Caractères | Bits |
|---|---|---|
| `Mode::Numeric` | `0`–`9` | 10 pour trois chiffres, 7 pour deux, 4 pour un |
| `Mode::Alphanumeric` | `0`–`9`, `A`–`Z`, espace et `$%*+-./:` | 11 pour deux caractères, 6 pour un |
| `Mode::Byte` | tout octet | 8 pour chaque octet |
| `Mode::Kanji` | les caractères à deux octets de Shift JIS (JIS X 0208) | 13 pour chaque caractère |

Chaque segment coûte son indicateur de mode et son nombre de caractères, dont la largeur grandit aux versions 10 et 27.
L'encodeur trouve la plus courte suite par programmation dynamique sur les caractères, avec les bits exacts de chaque
choix — le groupe inachevé de chiffres ou de caractères alphanumériques, l'en-tête de chaque nouveau segment,
l'en-tête ECI —, pour la plage de versions qu'il essaie. `HTTPS://EXAMPLE.COM/` suivi de quarante chiffres donne un
segment alphanumérique et un segment numérique, et une adresse en minuscules suivie de deux chiffres reste un seul
segment d'octets.

`Segment` construit des segments à la main — `numeric()`, `alphanumeric()`, `bytes()`, `kanji()`, `eci()` — et
`QrCode::fromSegments()` les encode dans leur ordre :

```php
QrCode::fromSegments([Segment::eci(Segment::UTF8), Segment::bytes('Prix : '), Segment::numeric('1250'), Segment::kanji('円')]);
```

## L'UTF-8 et l'en-tête ECI

Un lecteur prend les octets pour de l'ISO-8859-1, sauf si un en-tête ECI nomme leur jeu de caractères. Quand un
segment d'octets contient un octet hors ASCII, le code commence par l'en-tête ECI de l'UTF-8, l'affectation 26, sur
douze bits ; `eci: false` l'omet pour les lecteurs qui le traitent mal. Un texte qui n'est pas de l'UTF-8 valide est
écrit en octets, sans ECI et sans kanji.

`kanji: false` écrit aussi les caractères de Shift JIS en octets UTF-8, pour les rares lecteurs sans mode kanji.

## Versions et niveaux

Un symbole a 21 × 21 modules à la version 1 et 177 × 177 à la version 40, quatre de plus par côté à chaque version. La
plus petite version à partir de `$minVersion` qui contient les segments au niveau demandé est utilisée ; `$minVersion`
garde à une même taille les codes imprimés à un même endroit.

| Niveau | Mots de code qui peuvent être perdus |
|---|---|
| `ErrorCorrection::Low` | environ 7 % |
| `ErrorCorrection::Medium` | environ 15 % |
| `ErrorCorrection::Quartile` | environ 25 % |
| `ErrorCorrection::High` | environ 30 %, pour un code imprimé petit ou sous un logo |

La version 40 contient au plus 7 089 chiffres, 4 296 caractères alphanumériques ou 2 953 octets au niveau L.
`DataTooLong` refuse les textes plus longs, et refuse tout texte de plus de 7 089 octets avant le moindre calcul.

## La zone d'un logo

`$logoZone`, de 0 à 0,3, est la part du côté gardée au centre pour un logo. Au-dessus de 0 :

- le niveau est relevé à `High` ;
- la zone est un carré centré d'un nombre impair de modules : elle repose ainsi sur le module central, à l'écart des
  motifs de repérage, des motifs de synchronisation et des informations de format et de version ;
- la version est la plus petite à partir de `$minVersion` dont chaque bloc perd au plus un quart de ses mots de code de
  correction sous la zone : la moitié de ce qu'un bloc peut corriger, l'autre moitié restant pour l'usure. Un grand
  logo donne un code plus grand.

`QrCode::$zone` est la `Zone` gardée, nulle sans logo : son `x`, son `y` et son `side` en modules, et
`contains($x, $y)`. `rows()` garde les vrais modules ; les moteurs laissent la zone claire et dessinent le logo
par-dessus : voir [Logos](logos.md).

## Masques

Chacun des huit masques inverse les modules de données là où sa condition est vraie. Celui de plus faible pénalité est
gardé : suites d'une même couleur, carrés de 2 × 2, motifs qui ressemblent à un motif de repérage, part de modules
foncés éloignée de la moitié. `$mask` en impose un, de 0 à 7, pour les tests ou pour des codes qui ne doivent pas
changer quand les règles sont affinées.

## Lire le symbole

`size()` est le nombre de modules par côté, `isDark($x, $y)` lit un module à partir du coin supérieur gauche, et
`rows()` donne chaque rangée sous forme de chaîne de `1` et de `0`. `version`, `level`, `segments`, `mask`, `zone` et
`data` disent comment il a été construit.

## Coût

L'encodage est linéaire selon le texte, et les huit masques sont évalués sur tout le symbole : une adresse courte prend
environ 10 ms, un code de version 40 environ un tiers de seconde (voir `tests/Benchmark/EncodeBench.php`). Un code est
dessiné une seule fois et conservé, pas dessiné pour chaque lecteur.
