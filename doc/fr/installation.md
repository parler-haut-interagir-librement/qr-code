<!-- phil-dev source-sha256=876d8b6bd595d4f90059a6a97ad3aced79d1401ace643ca7ce51ff8f82f5f89b -->
# Installation

```sh
composer require phil/qr-code
```

Le paquet exige PHP 8.5 ou plus récent avec `ext-ctype`, `ext-filter`, `ext-mbstring`, `ext-sodium` et `ext-zlib`.
`ext-gd` est suggéré : `PngWriter` n'en a besoin que pour dessiner un logo sur un PNG. Le paquet n'a aucune autre
dépendance et n'appelle aucun service.
