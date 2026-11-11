<!-- phil-dev source-sha256=e22547a9f1345dbf8f962e7cac3bad9b771f59a2ddf122cec2e9110c230d268e -->
# phil/qr-code

**Des codes QR dessinés en SVG ou en PNG par PHP seul, sans service et sans bibliothèque d'images.**

phil/qr-code encode un texte en code QR (modèle 2, ISO/IEC 18004) et le dessine en image SVG ou PNG. Le texte est
découpé en la plus courte suite de segments numériques, alphanumériques, d'octets et de kanji, annoncé comme UTF-8 par
un en-tête ECI quand ses octets l'exigent, placé dans la plus petite des 40 versions qui le contient au niveau de
correction d'erreurs demandé, protégé par des codes de Reed-Solomon et disposé sous le masque de plus faible pénalité.
Un code peut garder une zone en son centre pour un logo, la version grandissant jusqu'à ce que chaque bloc puisse la
corriger. Des contenus typés écrivent des adresses, des contacts, des réseaux Wi-Fi, des événements, des lieux, des
messages et des numéros sous les formes sur lesquelles agissent les lecteurs des téléphones, échappés comme l'exigent
leurs formats.

Il s'adresse à tout code PHP qui imprime ou affiche un code QR : un billet, un reçu, une carte-cadeau, une affiche, un
bloc de page, un document imprimé. Rien n'est envoyé nulle part : le texte d'un code peut être privé, et il ne quitte
jamais le serveur.

C'est une simple bibliothèque PHP, écrite de zéro, sur `ext-mbstring`, `ext-sodium` et `ext-zlib` ; `ext-gd` ne sert
qu'à dessiner un logo sur un PNG. Sa sortie est déterministe : le même texte et les mêmes options donnent les mêmes
octets.

## État

| Partie | État |
|---|---|
| Segments numériques, alphanumériques, d'octets et de kanji ; la plus courte suite trouvée exactement pour chaque plage de versions | Implémenté et testé |
| L'en-tête ECI de l'UTF-8 ; des segments construits par l'appelant | Implémenté et testé |
| Versions 1 à 40, niveaux L, M, Q et H, une version minimale pour les codes imprimés à une même taille | Implémenté et testé |
| Codes de Reed-Solomon, entrelacement des blocs, informations de format et de version | Implémenté et testé |
| Les huit masques et les quatre règles de pénalité, ou un masque choisi par l'appelant | Implémenté et testé |
| La zone d'un logo, avec le niveau relevé et la version choisie pour que chaque bloc la corrige | Implémenté et testé |
| `SvgWriter` : un seul chemin, zone de silence, couleurs, taille, un titre pour les lecteurs d'écran, un logo | Implémenté et testé |
| `PngWriter` : un bit par pixel, écrit par la bibliothèque ; un logo dessiné avec GD | Implémenté et testé |
| Contenus typés : URL, mailto, tel, SMS, geo, Wi-Fi, MeCard, vCard 3.0 et 4.0, événement iCalendar | Implémenté, testé et soumis au fuzzing |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Encodage](doc/fr/encoding.md) | Segments, ECI, versions, niveaux, zone d'un logo, masques, limites. |
| [Contenus typés](doc/fr/contents.md) | Chaque contenu, sa charge utile, ses échappements et son libellé. |
| [SVG](doc/fr/svg.md) | L'image que dessine `SvgWriter`, ses options et son accessibilité. |
| [PNG](doc/fr/png.md) | L'image qu'écrit `PngWriter`, et ses options. |
| [Logos](doc/fr/logos.md) | La zone, les images acceptées, la façon dont chaque moteur les dessine. |
| [Points d'extension](doc/fr/extension-points.md) | Ce que l'application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, son lecteur, son fuzzing, ses bancs d'essai et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/qr-code
```

```php
use Phil\QrCode\Content\Wifi;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\PngWriter;
use Phil\QrCode\QrCode;
use Phil\QrCode\SvgWriter;

$code = QrCode::encode('https://example.com/tickets/42', ErrorCorrection::Quartile);
$svg = new SvgWriter()->write($code, 'Ticket 42');   // <svg viewBox="0 0 37 37" role="img">…
$png = new PngWriter(8)->write($code);               // the bytes of a PNG file

$wifi = new Wifi('Gnome Café', 'tea-and-dice');
$svg = new SvgWriter()->write(QrCode::encode($wifi), $wifi->label());
```

## Prérequis

PHP 8.5 ou plus récent, avec `ext-mbstring`, `ext-sodium` et `ext-zlib` ; `ext-gd` pour dessiner un logo sur un PNG.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
