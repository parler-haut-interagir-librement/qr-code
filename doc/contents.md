# Typed contents

A typed content writes what a code holds in the form phone readers act on, with the escapes its format requires.
Each implements `Contract\Content`: `payload()` is the text of the code, `label()` a short text that says what it
holds — to show beside the code or to name it for screen readers, never a password. `QrCode::encode()` takes one in
place of a text.

```php
QrCode::encode(new Wifi('Gnome Café', 'tea-and-dice', WifiSecurity::Wpa));
```

Every text field must be UTF-8 of at most 4 096 bytes without control characters — line breaks and tabs allowed in
the fields of several lines, a note, a description, a body —; `InvalidContent` refuses anything else, and any field
out of its form.

| Content | Payload | Escapes and checks |
|---|---|---|
| `Url` | the address | schemes `https` and `http` unless others are listed; a host required, in ASCII (IDNA form); spaces, bytes beyond ASCII, double quotes, angle brackets, backslash, caret, backtick, braces and the vertical bar percent-encoded |
| `Email` | `mailto:a@example.com?subject=…&body=…&cc=…&bcc=…` (RFC 6068) | addresses checked; subject, body and copies percent-encoded; the body's line breaks sent as CRLF |
| `Phone` | `tel:+15551234567` (RFC 3966) | digits, a leading `+`, the separators `-.()`; spaces dropped |
| `Sms` | `sms:+15551234567?body=…` (RFC 5724) | the number as `Phone`, the body percent-encoded |
| `Geo` | `geo:46.8139,-71.208,100;u=25` (RFC 5870) | latitude, longitude, altitude and uncertainty in range, at most seven decimals, never an exponent; a `name` for `label()` only |
| `Wifi` | `WIFI:T:WPA;S:name;P:key;H:true;;` | `\ ; , : "` escaped by a backslash; the name 1 to 32 bytes; the key of WPA (8 to 63 characters, or 64 hexadecimal digits), of WEP (5 or 13 characters, or 10 or 26 hexadecimal digits), none for `WifiSecurity::Open` |
| `MeCard` | `MECARD:N:name;TEL:…;EMAIL:…;URL:…;ADR:…;NOTE:…;BDAY:YYYYMMDD;;` | `\ ; , : "` escaped by a backslash |
| `VCard` | a vCard 3.0 (RFC 2426) or 4.0 (RFC 6350), `VCardVersion` | `\`, `,`, `;` and line breaks escaped in text values, `PostalAddress` in the seven components of `ADR`, lines ended by CRLF and folded before 75 octets, never inside a character |
| `Event` | an iCalendar `VEVENT` (RFC 5545) | text values escaped as in a vCard, instants in UTC, whole days as dates, the end after the start, lines folded |

The label of a `Wifi` is the name of the network; of a `VCard`, a `MeCard` and an `Event`, the name or the summary;
of the others, the address or the number.
