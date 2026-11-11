<!-- phil-dev source-sha256=755a82c5c752c43831ef6d02eb0b7bb7b66c3738722a8b047d17ba2a0a9ad557 -->
# SVG

`SvgWriter` dessine un `QrCode` sous forme d'image SVG autonome.

```php
new SvgWriter(int $quietZone = 4, string $dark = '#000000', string $light = '#ffffff', int $size = 0)
    ->write(QrCode $code, string $title = '', ?Logo $logo = null): string
```

L'image est un carré clair de la taille du code plus la zone de silence de chaque côté, et un seul chemin de carrés
foncés, un sous-chemin pour chaque suite de modules d'une rangée, en unités d'un module : `viewBox="0 0 29 29"` pour un
code de version 1 avec la zone de silence standard. `shape-rendering="crispEdges"` garde les bords nets à toute taille.

| Option | Plage |
|---|---|
| `$quietZone` | De 0 à 16 modules ; les lecteurs en demandent 4 autour d'un code imprimé sur un fond chargé |
| `$dark`, `$light` | `#rrggbb` ; gardez un fort contraste, foncé sur clair |
| `$size` | De 0 à 4096 : la `width` et la `height` écrites, en pixels ; 0 n'en écrit aucune, et l'image prend la taille que lui donne son conteneur |

`InvalidOption` refuse toute autre valeur.

La zone d'un code encodé avec `$logoZone` est laissée claire ; un `Logo` donné à `write()` est dessiné par-dessus, à un
demi-module de ses bords : voir [Logos](logos.md). Un logo pour un code sans zone est refusé.

## Accessibilité

L'image porte `role="img"` et un `<title>` : le `$title` donné, ou le texte propre du code. Les lecteurs d'écran
l'annoncent ; une page qui affiche l'adresse à côté du code peut donner un titre plus court, comme « code QR du
billet ». Le texte d'un code Wi-Fi contient son mot de passe : donnez à un tel code le `label()` de son contenu comme
titre.

## Sécurité

Le seul texte écrit est le titre, échappé pour XML. Un logo est intégré sous forme d'URI `data:` dans un élément
`<image>`, où un logo SVG n'exécute aucun script et ne charge rien. L'image ne contient ni script, ni lien, ni feuille
de style, ni référence externe : vous pouvez donc l'écrire en ligne dans une page et la conserver dans une base de
données.
