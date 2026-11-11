<!-- phil-dev source-sha256=9714fc98f69a2bdb0d6fdd55df01cc803bdcb0527404a95e7538de3036c31be7 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Dessiner un code autrement — PDF, terminal | Lisez `QrCode::rows()` ou `isDark()`, et `zone`, et dessinez les modules ; `SvgWriter` et `PngWriter` sont deux de ces dessins |
| Écrire un autre genre de contenu | Implémentez `Contract\Content` : son `payload()`, échappé comme l'exige son format, et un `label()` sans secret |
| Choisir les segments | Construisez des `Segment` et passez-les à `QrCode::fromSegments()` |
| Garder un code à la même taille quel que soit son texte | Passez `$minVersion` à `QrCode::encode()` |
| Garder un code identique d'une version du paquet à l'autre | Passez `$mask` à `QrCode::encode()` |
| Laisser de la place pour un logo | Passez `$logoZone` à `QrCode::encode()`, et un `Logo` au moteur |

Les classes sous `Internal` ne sont pas des points d'extension.
