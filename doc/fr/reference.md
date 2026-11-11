<!-- phil-dev source-sha256=d6c5bf303c38e378b60c10d7fa871f27254515bfc113bb3d2fd6469d0a16fa68 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `QrCode` | Un symbole : `encode()`, `fromSegments()`, `size()`, `isDark()`, `rows()`, et comment il a été construit. |
| `Segment` | Une suite des données dans un mode, ou un en-tête ECI. |
| `Mode` | Les modes numérique, alphanumérique, octets et kanji, et l'en-tête ECI. |
| `ErrorCorrection` | Les quatre niveaux de correction d'erreurs, `L`, `M`, `Q` et `H`. |
| `Zone` | Le carré qu'un code garde en son centre pour un logo. |
| `Logo` | Une image PNG, JPEG, WebP ou SVG à dessiner dans la zone. |
| `SvgWriter` | Dessine un `QrCode` sous forme d'image SVG. |
| `PngWriter` | Dessine un `QrCode` sous forme d'image PNG. |
| `Contract\Content` | Ce que donne un contenu typé : sa charge utile et son libellé. |
| `Content\Url`, `Email`, `Phone`, `Sms`, `Geo` | Des adresses à ouvrir, à qui écrire, à appeler, à texter, ou un lieu. |
| `Content\Wifi`, `WifiSecurity` | Un réseau à joindre, et sa protection. |
| `Content\MeCard`, `VCard`, `VCardVersion`, `PostalAddress` | Un contact, sous la forme compacte MeCard ou en vCard. |
| `Content\Event` | Un événement à ajouter à un calendrier. |
| `Exception\QrCodeException` | Implémentée par chaque exception du paquet. |
| `Exception\DataTooLong` | Les données n'entrent pas dans la version 40 au niveau demandé. |
| `Exception\InvalidOption` | Une version, un masque, une zone, un segment, une zone de silence, une couleur, une échelle, une taille ou un logo hors de sa plage. |
| `Exception\InvalidContent` | Un champ d'un contenu typé qui ne peut pas être écrit. |
