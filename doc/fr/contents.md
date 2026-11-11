<!-- phil-dev source-sha256=58401bbaa08bc1322c52fb3c4057fc422a67445dc849ff6d4f8a1c8b5f04ab03 -->
# Contenus typés

Un contenu typé écrit ce que contient un code sous la forme sur laquelle agissent les lecteurs des téléphones, avec les
échappements qu'exige son format. Chacun implémente `Contract\Content` : `payload()` est le texte du code, `label()` un
court texte qui dit ce qu'il contient — à montrer à côté du code ou pour le nommer aux lecteurs d'écran, jamais un mot
de passe. `QrCode::encode()` en accepte un à la place d'un texte.

```php
QrCode::encode(new Wifi('Gnome Café', 'tea-and-dice', WifiSecurity::Wpa));
```

Chaque champ de texte doit être de l'UTF-8 d'au plus 4 096 octets sans caractère de contrôle — sauts de ligne et
tabulations permis dans les champs de plusieurs lignes, une note, une description, un corps. `InvalidContent` refuse
tout le reste, ainsi que tout champ qui sort de sa forme.

| Contenu | Charge utile | Échappements et vérifications |
|---|---|---|
| `Url` | l'adresse | schémas `https` et `http`, sauf si d'autres sont listés ; un hôte exigé, en ASCII (forme IDNA) ; espaces, octets hors ASCII, guillemets doubles, chevrons, barre oblique inverse, accent circonflexe, accent grave, accolades et barre verticale encodés en pourcentage |
| `Email` | `mailto:a@example.com?subject=…&body=…&cc=…&bcc=…` (RFC 6068) | adresses vérifiées ; objet, corps et copies encodés en pourcentage ; les sauts de ligne du corps envoyés en CRLF |
| `Phone` | `tel:+15551234567` (RFC 3966) | chiffres, un `+` initial, les séparateurs `-.()` ; espaces retirées |
| `Sms` | `sms:+15551234567?body=…` (RFC 5724) | le numéro comme pour `Phone`, le corps encodé en pourcentage |
| `Geo` | `geo:46.8139,-71.208,100;u=25` (RFC 5870) | latitude, longitude, altitude et incertitude dans leurs plages, au plus sept décimales, jamais d'exposant ; un `name` pour `label()` seulement |
| `Wifi` | `WIFI:T:WPA;S:name;P:key;H:true;;` | `\ ; , : "` échappés par une barre oblique inverse ; le nom de 1 à 32 octets ; la clé du WPA (de 8 à 63 caractères, ou 64 chiffres hexadécimaux), du WEP (5 ou 13 caractères, ou 10 ou 26 chiffres hexadécimaux), aucune pour `WifiSecurity::Open` |
| `MeCard` | `MECARD:N:name;TEL:…;EMAIL:…;URL:…;ADR:…;NOTE:…;BDAY:YYYYMMDD;;` | `\ ; , : "` échappés par une barre oblique inverse |
| `VCard` | une vCard 3.0 (RFC 2426) ou 4.0 (RFC 6350), `VCardVersion` | `\`, `,`, `;` et sauts de ligne échappés dans les valeurs de texte, `PostalAddress` dans les sept composantes de `ADR`, lignes terminées par CRLF et repliées avant 75 octets, jamais au milieu d'un caractère |
| `Event` | un `VEVENT` iCalendar (RFC 5545) | valeurs de texte échappées comme dans une vCard, instants en UTC, journées entières sous forme de dates, la fin après le début, lignes repliées |

Le libellé d'un `Wifi` est le nom du réseau ; celui d'une `VCard`, d'une `MeCard` et d'un `Event`, le nom ou le
résumé ; celui des autres, l'adresse ou le numéro.
