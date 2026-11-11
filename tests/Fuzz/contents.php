<?php

declare(strict_types=1);

/*
 * The typed contents on fields nobody wrote: each field of the input, split on line feeds, goes into every content;
 * a content either refuses it with InvalidContent, or writes a payload that is UTF-8, whose fields read back as they
 * were given, and that a QR code holds or refuses with DataTooLong.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\QrCode\Content\Email;
use Phil\QrCode\Content\Event;
use Phil\QrCode\Content\MeCard;
use Phil\QrCode\Content\Sms;
use Phil\QrCode\Content\Url;
use Phil\QrCode\Content\VCard;
use Phil\QrCode\Content\Wifi;
use Phil\QrCode\Content\WifiSecurity;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\DataTooLong;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\QrCode;
use Phil\QrCode\Tests\Support\Fields;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    [$a, $b, $c] = explode("\x1F", $input, 3) + ['', '', ''];
    $builders = [
        'wifi' => static fn (): Content => new Wifi($a, $b, WifiSecurity::cases()[strlen($c) % 3], '' !== $c),
        'mecard' => static fn (): Content => new MeCard($a, [], [], '', $b, $c),
        'vcard' => static fn (): Content => new VCard($a, $b, $c, note: $c),
        'event' => static fn (): Content => new Event($a, new DateTimeImmutable('@0'), null, $b, $c),
        'url' => static fn (): Content => new Url($a),
        'email' => static fn (): Content => new Email('a@example.com', $a, $b),
        'sms' => static fn (): Content => new Sms('+1555', $a),
    ];

    foreach ($builders as $name => $build) {
        try {
            $content = $build();
        } catch (InvalidContent) {
            // The error a content promises for a field it cannot write.
            continue;
        }

        $payload = $content->payload();

        if (!mb_check_encoding($payload, 'UTF-8')) {
            throw new LogicException($name.' wrote a payload that is not UTF-8.');
        }

        $read = match ($name) {
            'wifi' => Fields::semicolons($payload)['S'][0] ?? null,
            'mecard' => Fields::semicolons($payload)['N'][0] ?? null,
            'vcard' => Fields::lines($payload)[3][1] ?? null,
            'event' => Fields::lines($payload)[1][1] ?? null,
            default => null,
        };

        if (null !== $read && $read !== str_replace(["\r\n", "\r"], "\n", $a)) {
            throw new LogicException($name.' does not read back its first field.');
        }

        try {
            QrCode::encode($content);
        } catch (DataTooLong) {
            // A content longer than a code holds.
        }
    }
});
$config->setMaxLen(1024);
