<!-- phil-dev source-sha256=8691876b51bf35fff17a2be3c1df11a48bb102386eb1222a6b162caae1b76bb8 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau. Elle vérifie les tables de capacité par rapport aux totaux
de la norme, les mots de code de Reed-Solomon de l'exemple `HELLO WORLD` de la norme en 1-Q, les kanji de l'exemple
`点茗` de la norme, les codes BCH des informations de format et de version, chaque masque et chaque règle de pénalité,
et des symboles entiers par rapport aux fichiers de référence de `tests/Unit/Fixture`.

Les symboles de référence, et un balayage de chaque version à chaque niveau, ont été relus par un décodeur indépendant
(ZXing) lors de leur écriture, sans l'en-tête ECI : un changement qui modifie un fichier de référence doit être vérifié
de la même façon avant de remplacer le fichier.

`tests/Support/Decoder` est un lecteur écrit à part de l'encodeur, qui ne partage avec lui que la table des blocs de la
norme. Il lit les informations de format, marque les motifs fonctionnels à partir de la table des centres d'alignement
de la norme, retire le masque, sépare les blocs, vérifie chacun par ses syndromes — ou en déduit les mots de code sous
un logo — et analyse les segments. Il lit les symboles de référence, et les tests relisent par lui des textes de chaque
mode, des codes dont la zone de logo est effacée, et des images PNG échantillonnées pixel par pixel.

La segmentation est vérifiée par rapport à chaque affectation d'un mode à chaque caractère de textes courts. Chaque
contenu typé est vérifié à l'octet près, et ses champs relus par `tests/Support/Fields`.

## Fuzzing et bancs d'essai

`composer fuzz` lance deux cibles : `contents`, les contenus typés sur des champs que personne n'a écrits, qui les
refusent ou écrivent une charge utile UTF-8 dont les champs se relisent ; et `encode`, des octets quelconques encodés
puis relus par le lecteur des tests. `tests/Benchmark/EncodeBench.php` mesure l'encodage d'une adresse, d'un texte
mixte, d'un code avec une zone de logo, d'un code de version 40, ainsi que les deux moteurs.

Dans le monodépôt, Infection tient le paquet à 100 % des mutants couverts.

## Tester du code qui dessine des codes

Un code QR est déterministe : comparez `rows()`, le SVG ou le PNG à une valeur à vous, ou vérifiez `version` et
`segments`.
