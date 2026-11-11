<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\QrCode\Content\Email;
use Phil\QrCode\Content\Event;
use Phil\QrCode\Content\Geo;
use Phil\QrCode\Content\MeCard;
use Phil\QrCode\Content\Phone;
use Phil\QrCode\Content\PostalAddress;
use Phil\QrCode\Content\Sms;
use Phil\QrCode\Content\Url;
use Phil\QrCode\Content\VCard;
use Phil\QrCode\Content\VCardVersion;
use Phil\QrCode\Content\Wifi;
use Phil\QrCode\Content\WifiSecurity;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;
use Phil\QrCode\Tests\Support\Fields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Url::class)]
#[CoversClass(Email::class)]
#[CoversClass(Phone::class)]
#[CoversClass(Sms::class)]
#[CoversClass(Geo::class)]
#[CoversClass(Wifi::class)]
#[CoversClass(WifiSecurity::class)]
#[CoversClass(MeCard::class)]
#[CoversClass(VCard::class)]
#[CoversClass(VCardVersion::class)]
#[CoversClass(PostalAddress::class)]
#[CoversClass(Event::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidContent::class)]
final class ContentTest extends TestCase
{
    public function testPercentEncodesAnAddress(): void
    {
        $url = new Url('https://example.com/a b/é?q="x"&r=<y>#{z}|^`\\');

        self::assertSame('https://example.com/a%20b/%C3%A9?q=%22x%22&r=%3Cy%3E#%7Bz%7D%7C%5E%60%5C', $url->payload());
        self::assertSame('https://example.com/a b/é?q="x"&r=<y>#{z}|^`\\', $url->label());
        self::assertSame('http://EXAMPLE.com:8080/~a-b_c.d!$&\'()*+,;=:@%41', new Url('http://EXAMPLE.com:8080/~a-b_c.d!$&\'()*+,;=:@%41')->payload());
        self::assertSame('ftp://example.com/f', new Url('ftp://example.com/f', ['ftp'])->payload());
        self::assertSame('HTTPS://EXAMPLE.COM', new Url('HTTPS://EXAMPLE.COM')->payload());
        self::assertSame('https://user@example.com', new Url('https://user@example.com')->payload());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'a script' => ['javascript:alert(1)', 'The address must be absolute, with a host, of the schemes https, http.'];
        yield 'data' => ['data:text/html,<b>', 'of the schemes https, http'];
        yield 'no host' => ['https:///path', 'with a host'];
        yield 'relative' => ['/path', 'absolute'];
        yield 'no scheme' => ['example.com', 'absolute'];
        yield 'a scheme without slashes' => ['https:example.com', 'absolute'];
        yield 'a host beyond ASCII' => ['https://exämple.com/', 'The host of the address must be ASCII: write an internationalised name in its IDNA form.'];
        yield 'a host with a space' => ['https://exa mple.com/', 'The host of the address must be ASCII'];
        yield 'a line break' => ["https://example.com/\n", 'The address must be text on one line, without control characters.'];
    }

    #[DataProvider('refusedAddresses')]
    public function testRefusesAnAddressItCannotWrite(string $url, string $message): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Url($url);
    }

    public function testWritesAMailtoAddress(): void
    {
        $email = new Email(['jane@example.com', 'a+b%c@example.org'], 'Hello & bye?', "Line 1\nLine 2\r\nLine 3", ['c@example.com', 'd@example.com'], ['e@example.com']);

        self::assertSame('mailto:jane@example.com,a+b%25c@example.org?subject=Hello%20%26%20bye%3F&body=Line%201%0D%0ALine%202%0D%0ALine%203&cc=c@example.com,d@example.com&bcc=e@example.com', $email->payload());
        self::assertSame('jane@example.com, a+b%c@example.org', $email->label());
        self::assertSame('mailto:jane@example.com', new Email('jane@example.com')->payload());
        self::assertSame('mailto:jane@example.com?body=x', new Email('jane@example.com', body: 'x')->payload());
        self::assertSame("Line 1\nLine 2\nLine 3", $email->body);
        self::assertSame('mailto:a@example.com?cc=b%25c@example.com&bcc=d%25e@example.com', new Email('a@example.com', cc: ['b%c@example.com'], bcc: ['d%e@example.com'])->payload());
        $longest = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61);
        self::assertSame(254, \strlen($longest));
        self::assertSame([$longest], new Email($longest)->to);
    }

    public function testRefusesAnEmailAddressThatIsNotOne(): void
    {
        foreach ([['not an address', 'recipient'], [str_repeat('a', 250).'@example.com', 'recipient']] as [$address, $field]) {
            try {
                new Email($address);
                self::fail($address);
            } catch (InvalidContent $invalidContent) {
                self::assertSame('The '.$field.' must be an e-mail address.', $invalidContent->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('The blind copy must be an e-mail address.');
        new Email('a@example.com', bcc: ['x']);
    }

    public function testRefusesACopyThatIsNotAnAddress(): void
    {
        $this->expectExceptionMessageIsOrContains('The copy must be an e-mail address.');

        new Email('a@example.com', cc: ['x']);
    }

    public function testRefusesASubjectOnTwoLines(): void
    {
        $this->expectExceptionMessageIsOrContains('The subject must be text on one line');

        new Email('a@example.com', "a\nb");
    }

    public function testWritesTelephoneAndMessageAddresses(): void
    {
        self::assertSame('tel:+1(555)123-4567', new Phone(' +1 (555) 123-4567 ')->payload());
        self::assertSame('+1.555.123', new Phone('+1.555.123')->label());
        self::assertSame('tel:911', new Phone('911')->payload());
        self::assertSame('sms:+15551234567?body=Rendez-vous%20%C3%A0%209%20h%3B%20ok%3F%0D%0Aoui', new Sms('+1 555 123 4567', "Rendez-vous à 9 h; ok?\noui")->payload());
        self::assertSame('sms:+15551234567', new Sms('+15551234567')->payload());
        self::assertSame('+15551234567', new Sms('+15551234567')->label());
        self::assertSame(str_repeat('1', 63), new Phone(str_repeat('1', 63))->number);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedNumbers(): iterable
    {
        yield 'letters' => ['555-CALL'];
        yield 'no digit' => ['+()-.'];
        yield 'empty' => [''];
        yield 'a plus inside' => ['1+2'];
        yield 'too long' => [str_repeat('1', 65)];
        yield 'a line break' => ["555\n1234"];
    }

    #[DataProvider('refusedNumbers')]
    public function testRefusesANumberThatIsNotOne(string $number): void
    {
        $this->expectException(InvalidContent::class);

        new Phone($number);
    }

    public function testNamesTheFieldOfAWrongNumber(): void
    {
        $this->expectExceptionMessageIsOrContains('The number of the message must be digits, a leading + and the separators - . ( ).');

        new Sms('x');
    }

    public function testWritesAGeoAddress(): void
    {
        self::assertSame('geo:46.8139,-71.208,100.5;u=25', new Geo(46.8139, -71.208, 100.5, 25)->payload());
        self::assertSame('geo:0,0', new Geo(-0.00000001, 0)->payload());
        self::assertSame('geo:-90,180', new Geo(-90, 180)->payload());
        self::assertSame('geo:90,-180,0;u=0', new Geo(90, -180, 0, 0)->payload());
        self::assertSame('geo:1.1234568,2', new Geo(1.123456789, 2.0)->payload());
        self::assertSame('geo:10,20,-5', new Geo(10, 20, -5)->payload());
        self::assertSame('46.8139, -71.208', new Geo(46.8139, -71.208)->label());
        self::assertSame('Québec', new Geo(46.8139, -71.208, name: 'Québec')->label());
        self::assertSame('geo:1,2,100000000;u=100000000', new Geo(1, 2, 100_000_000, 100_000_000)->payload());
        self::assertSame('geo:1,2,-100000000', new Geo(1, 2, -100_000_000)->payload());
    }

    /**
     * @return iterable<string, array{float, float, ?float, ?float, string}>
     */
    public static function refusedPlaces(): iterable
    {
        yield 'latitude below' => [-90.0001, 0, null, null, 'The latitude must be from -90 to 90 degrees.'];
        yield 'latitude above' => [90.0001, 0, null, null, 'latitude'];
        yield 'latitude not a number' => [\NAN, 0, null, null, 'latitude'];
        yield 'longitude below' => [0, -180.0001, null, null, 'The longitude must be from -180 to 180 degrees.'];
        yield 'longitude above' => [0, 180.0001, null, null, 'longitude'];
        yield 'longitude infinite' => [0, \INF, null, null, 'longitude'];
        yield 'altitude infinite' => [0, 0, \INF, null, 'The altitude must be a finite number of metres.'];
        yield 'altitude too high' => [0, 0, 100_000_001, null, 'altitude'];
        yield 'altitude too deep' => [0, 0, -100_000_001, null, 'altitude'];
        yield 'uncertainty negative' => [0, 0, null, -1, 'The uncertainty must be a positive number of metres.'];
        yield 'uncertainty too large' => [0, 0, null, 100_000_001, 'uncertainty'];
        yield 'uncertainty not a number' => [0, 0, null, \NAN, 'uncertainty'];
    }

    #[DataProvider('refusedPlaces')]
    public function testRefusesACoordinateOutOfRange(float $latitude, float $longitude, ?float $altitude, ?float $uncertainty, string $message): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Geo($latitude, $longitude, $altitude, $uncertainty);
    }

    public function testWritesAWifiNetworkWithItsSpecialCharactersEscaped(): void
    {
        $wifi = new Wifi('Café; "Gnome"', 'pa:ss\\wo,rd', WifiSecurity::Wpa, true);

        self::assertSame('WIFI:T:WPA;S:Café\; \\"Gnome\\";P:pa\\:ss\\\\wo\\,rd;H:true;;', $wifi->payload());
        self::assertSame(['T' => ['WPA'], 'S' => ['Café; "Gnome"'], 'P' => ['pa:ss\\wo,rd'], 'H' => ['true']], Fields::semicolons($wifi->payload()));
        self::assertSame('Café; "Gnome"', $wifi->label());
        self::assertSame('WIFI:T:WEP;S:ABCDEF;P:0123456789;;', new Wifi('ABCDEF', '0123456789', WifiSecurity::Wep)->payload());
        self::assertSame('WIFI:T:nopass;S:Open;;', new Wifi('Open', '', WifiSecurity::Open)->payload());
        self::assertSame('WIFI:T:WPA;S:N;P:12345678;;', new Wifi('N', '12345678')->payload());
        self::assertSame(str_repeat('s', 32), new Wifi(str_repeat('s', 32), '12345678')->ssid);
    }

    /**
     * @return iterable<string, array{string, WifiSecurity}>
     */
    public static function acceptedKeys(): iterable
    {
        yield 'WPA, 8 characters' => [str_repeat('é', 8), WifiSecurity::Wpa];
        yield 'WPA, 63 characters' => [str_repeat('a', 63), WifiSecurity::Wpa];
        yield 'WPA, 64 hexadecimal digits' => [str_repeat('aF', 32), WifiSecurity::Wpa];
        yield 'WEP, 5 characters' => ['abcde', WifiSecurity::Wep];
        yield 'WEP, 13 characters' => [str_repeat('z', 13), WifiSecurity::Wep];
        yield 'WEP, 10 hexadecimal digits' => ['0123456789', WifiSecurity::Wep];
        yield 'WEP, 26 hexadecimal digits' => [str_repeat('ab', 13), WifiSecurity::Wep];
        yield 'open, none' => ['', WifiSecurity::Open];
        yield 'WPA, 40 characters of 80 bytes' => [str_repeat('é', 40), WifiSecurity::Wpa];
    }

    #[DataProvider('acceptedKeys')]
    public function testAcceptsTheKeysOfEachNetwork(string $password, WifiSecurity $security): void
    {
        self::assertSame($password, new Wifi('net', $password, $security)->password);
    }

    /**
     * @return iterable<string, array{string, string, WifiSecurity, string}>
     */
    public static function refusedNetworks(): iterable
    {
        yield 'no name' => ['', 'password1', WifiSecurity::Wpa, 'The name of the network must be from 1 to 32 bytes.'];
        yield 'a name of 33 bytes' => [str_repeat('a', 33), 'password1', WifiSecurity::Wpa, 'from 1 to 32 bytes'];
        yield 'WPA, 7 characters' => ['n', '1234567', WifiSecurity::Wpa, 'The password of the network must be 8 to 63 characters, or 64 hexadecimal digits, for WPA.'];
        yield 'WPA, 64 characters not hexadecimal' => ['n', str_repeat('g', 64), WifiSecurity::Wpa, 'for WPA'];
        yield 'WPA, 65 hexadecimal digits' => ['n', str_repeat('a', 65), WifiSecurity::Wpa, 'for WPA'];
        yield 'WEP, 6 characters' => ['n', 'abcdef', WifiSecurity::Wep, 'The password of the network must be 5 or 13 characters, or 10 or 26 hexadecimal digits, for WEP.'];
        yield 'WEP, 10 characters not hexadecimal' => ['n', 'ghijklmnop', WifiSecurity::Wep, 'for WEP'];
        yield 'WEP, 26 characters not hexadecimal' => ['n', str_repeat('g', 26), WifiSecurity::Wep, 'for WEP'];
        yield 'WEP, 10 characters ending in hexadecimal' => ['n', 'g123456789', WifiSecurity::Wep, 'for WEP'];
        yield 'WEP, 10 characters starting in hexadecimal' => ['n', '012345678g', WifiSecurity::Wep, 'for WEP'];
        yield 'open, a password' => ['n', 'x', WifiSecurity::Open, 'The password of the network must be empty for an open network.'];
        yield 'a password with a line break' => ['n', "pass\nword", WifiSecurity::Wpa, 'The password of the network must be text on one line'];
    }

    #[DataProvider('refusedNetworks')]
    public function testRefusesANetworkItCannotWrite(string $ssid, string $password, WifiSecurity $security, string $message): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Wifi($ssid, $password, $security);
    }

    public function testWritesAMeCard(): void
    {
        $card = new MeCard('Doe, Jane', ['+1 555 123 4567', '911'], ['jane@example.com'], 'https://example.com', '1 Main St; Québec', 'Hi: "there"', new DateTimeImmutable('1990-03-04'));

        self::assertSame('MECARD:N:Doe\\, Jane;TEL:+15551234567;TEL:911;EMAIL:jane@example.com;URL:https\\://example.com;ADR:1 Main St\; Québec;NOTE:Hi\\: \\"there\\";BDAY:19900304;;', $card->payload());
        self::assertSame(['N' => ['Doe, Jane'], 'TEL' => ['+15551234567', '911'], 'EMAIL' => ['jane@example.com'], 'URL' => ['https://example.com'], 'ADR' => ['1 Main St; Québec'], 'NOTE' => ['Hi: "there"'], 'BDAY' => ['19900304']], Fields::semicolons($card->payload()));
        self::assertSame('Doe, Jane', $card->label());
        self::assertSame('MECARD:N:Jane;;', new MeCard('Jane')->payload());
    }

    public function testRefusesAMeCardWithoutAName(): void
    {
        $this->expectExceptionMessageIsOrContains('The name must be a text.');

        new MeCard('  ');
    }

    public function testWritesAVCard3(): void
    {
        $card = new VCard(
            'Jane Doe',
            'Doe',
            'Jane',
            'ACME; Inc.',
            'Boss, big',
            ['+1 555 123 4567'],
            ['jane@example.com'],
            ['https://example.com/a b'],
            new PostalAddress('1 Main St; app. 2', 'Québec', 'QC', 'G1A 1A1', 'Canada'),
            new DateTimeImmutable('1990-03-04'),
            "Line one\\\nline two",
        );

        self::assertSame(implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:Doe;Jane;;;',
            'FN:Jane Doe',
            'ORG:ACME\; Inc.',
            'TITLE:Boss\\, big',
            'TEL:+15551234567',
            'EMAIL:jane@example.com',
            'URL:https://example.com/a%20b',
            'ADR:;;1 Main St\; app. 2;Québec;QC;G1A 1A1;Canada',
            'BDAY:1990-03-04',
            'NOTE:Line one\\\\\\nline two',
            'END:VCARD',
        ]), $card->payload());
        self::assertSame('Jane Doe', $card->label());
        self::assertSame("BEGIN:VCARD\r\nVERSION:3.0\r\nN:;;;;\r\nFN:Jane\r\nEND:VCARD", new VCard('Jane')->payload());
    }

    public function testWritesAVCard4(): void
    {
        $payload = new VCard('Jane Doe', phones: ['+15551234567'], birthday: new DateTimeImmutable('1990-03-04'), version: VCardVersion::V4)->payload();

        self::assertSame("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Jane Doe\r\nTEL;VALUE=uri:tel:+15551234567\r\nBDAY:19900304\r\nEND:VCARD", $payload);
        self::assertStringContainsString("\r\nN:Doe;;;;\r\n", new VCard('Jane Doe', 'Doe', version: VCardVersion::V4)->payload());
        self::assertStringContainsString("\r\nN:;Jane;;;\r\n", new VCard('Jane Doe', givenName: 'Jane', version: VCardVersion::V4)->payload());
    }

    public function testFoldsLongLinesWithoutCuttingACharacter(): void
    {
        $note = str_repeat('éèà, ', 40);
        $payload = new VCard('Jane', note: $note)->payload();

        foreach (explode("\r\n", $payload) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line));
            self::assertTrue(mb_check_encoding(ltrim($line, ' '), 'UTF-8'), $line);
        }

        self::assertContains(['NOTE', $note], Fields::lines($payload));
        self::assertSame(['NOTE', str_repeat('a', 200)], Fields::lines(new VCard('J', note: str_repeat('a', 200))->payload())[4]);
        self::assertSame('NOTE:'.str_repeat('a', 70)."\r\n ".str_repeat('a', 74)."\r\n ".str_repeat('a', 56), explode("\r\nEND", substr(new VCard('J', note: str_repeat('a', 200))->payload(), (int) strpos(new VCard('J', note: str_repeat('a', 200))->payload(), 'NOTE')))[0]);
        self::assertStringContainsString("\r\nNOTE:".str_repeat('a', 70)."\r\nEND", new VCard('J', note: str_repeat('a', 70))->payload());
        // The 75th octet is the second of an é: the line is cut before the é.
        self::assertStringContainsString("\r\nNOTE:".str_repeat('a', 69)."\r\n é".str_repeat('b', 10)."\r\nEND", new VCard('J', note: str_repeat('a', 69).'é'.str_repeat('b', 10))->payload());
        // A three-octet character over the cut: two continuation octets stepped over.
        self::assertStringContainsString("\r\nNOTE:".str_repeat('a', 69)."\r\n ✓b\r\nEND", new VCard('J', note: str_repeat('a', 69).'✓b')->payload());
        self::assertStringContainsString("\r\nNOTE:".str_repeat('a', 70)."\r\n é\r\nEND", new VCard('J', note: str_repeat('a', 70).'é')->payload());
    }

    public function testRefusesAVCardWithoutAName(): void
    {
        $this->expectExceptionMessageIsOrContains('The name must be a text.');

        new VCard('  ');
    }

    public function testRefusesAVCardSiteThatIsNotAnAddress(): void
    {
        $this->expectExceptionMessageIsOrContains('The address must be absolute');

        new VCard('Jane', urls: ['javascript:x']);
    }

    public function testWritesAnEventInUtc(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $event = new Event('Partie de JDR, session 3', new DateTimeImmutable('2026-11-08 19:00', $zone), new DateTimeImmutable('2026-11-08 23:00', $zone), 'Salle 2; sous-sol', "Apporter des dés\net du café", 'https://example.com/e', uid: 'e-42@example.com');

        self::assertSame(implode("\r\n", [
            'BEGIN:VEVENT',
            'UID:e-42@example.com',
            'SUMMARY:Partie de JDR\\, session 3',
            'DTSTART:20261109T000000Z',
            'DTEND:20261109T040000Z',
            'LOCATION:Salle 2\; sous-sol',
            'DESCRIPTION:Apporter des dés\\net du café',
            'URL:https://example.com/e',
            'END:VEVENT',
        ]), $event->payload());
        self::assertSame('Partie de JDR, session 3', $event->label());
        self::assertSame("BEGIN:VEVENT\r\nSUMMARY:A\r\nDTSTART:20261108T120000Z\r\nEND:VEVENT", new Event('A', new DateTimeImmutable('2026-11-08 12:00:00', new DateTimeZone('UTC')))->payload());
    }

    public function testWritesAnEventOfWholeDays(): void
    {
        $zone = new DateTimeZone('Pacific/Auckland');
        $event = new Event('Convention', new DateTimeImmutable('2026-11-08 00:00', $zone), new DateTimeImmutable('2026-11-10 00:00', $zone), allDay: true);

        self::assertStringContainsString("\r\nDTSTART;VALUE=DATE:20261108\r\nDTEND;VALUE=DATE:20261110\r\n", $event->payload());
    }

    public function testRefusesAnEndBeforeTheStart(): void
    {
        $start = new DateTimeImmutable('2026-11-08 12:00:00');

        foreach ([[$start, false], [$start->modify('-1 second'), false], [$start->modify('+11 hours'), true]] as [$end, $allDay]) {
            try {
                new Event('A', $start, $end, allDay: $allDay);
                self::fail('An end at '.$end->format('c').' is refused.');
            } catch (InvalidContent $invalidContent) {
                self::assertSame('The end of the event must be after its start.', $invalidContent->getMessage());
            }
        }

        self::assertNotNull(new Event('A', $start, $start->modify('+1 second'))->end);
        self::assertNotNull(new Event('A', $start, $start->modify('+12 hours'), allDay: true)->end);
        self::assertNotNull(new Event('A', $start, $start->modify('+1 day'), allDay: true)->end);
    }

    public function testRefusesAnEventWithoutASummary(): void
    {
        $this->expectExceptionMessageIsOrContains('The summary must be a text.');

        new Event(' ', new DateTimeImmutable());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function unclean(): iterable
    {
        yield 'not UTF-8' => ["\xFF", false];
        yield 'a null byte' => ["a\0b", true];
        yield 'an escape' => ["a\x1Bb", true];
        yield 'a delete' => ["a\x7Fb", true];
        yield 'a C1 control' => ["a\u{85}b", true];
        yield 'a vertical tab' => ["a\x0Bb", true];
        yield 'too long' => [str_repeat('a', 4097), false];
    }

    #[DataProvider('unclean')]
    public function testRefusesTextThatIsNotClean(string $text, bool $multiline): void
    {
        $this->expectException(InvalidContent::class);

        Text::clean($text, 'note', $multiline);
    }

    public function testKeepsLineBreaksAndTabsOfMultiLineFields(): void
    {
        self::assertSame("a\nb\n\tc\nd", Text::clean("a\r\nb\r\tc\nd", 'note', true));
        self::assertSame(str_repeat('a', 4096), Text::clean(str_repeat('a', 4096), 'note'));
        self::assertSame('The note must be UTF-8 text of at most 4096 bytes.', $this->message("\xFF"));
        self::assertSame('The note must be text without control characters but line breaks and tabs.', $this->message("\0", true));
    }

    public function testPercentEncodesEveryByteButTheUnreserved(): void
    {
        self::assertSame('aZ09-._~%20%2F%3F%C3%A9', Text::percent('aZ09-._~ /?é'));
        self::assertSame('a@b%2Cc', Text::percent('a@b,c', '@'));
        self::assertSame('a%5Db', Text::percent('a]b', ''));
        self::assertSame('a]b/c^d%5C', Text::percent('a]b/c^d\\', ']/^'));
    }

    private function message(string $text, bool $multiline = false): string
    {
        try {
            Text::clean($text, 'note', $multiline);
        } catch (InvalidContent $invalidContent) {
            return $invalidContent->getMessage();
        }

        return '';
    }
}
