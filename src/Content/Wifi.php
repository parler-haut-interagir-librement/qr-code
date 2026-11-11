<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Internal\Text;

/**
 * A network to join: `WIFI:T:WPA;S:name;P:password;H:true;;`, the form phone cameras read. Backslash, semicolon,
 * comma, colon and double quote are escaped by a backslash. A WPA key of 64 hexadecimal digits, or a WEP key of 10
 * or 26, is written as it is, and read as the key itself.
 *
 * `label()` gives the name of the network, never its password.
 */
final readonly class Wifi implements Content
{
    public string $ssid;

    public string $password;

    /**
     * @param string $ssid     the name of the network, 1 to 32 bytes
     * @param string $password the key: 8 to 63 characters, or 64 hexadecimal digits, for WPA; 5 or 13 characters, or
     *                         10 or 26 hexadecimal digits, for WEP; none for an open network
     *
     * @throws InvalidContent when the name or the password does not suit the network
     */
    public function __construct(
        string $ssid,
        string $password = '',
        public WifiSecurity $security = WifiSecurity::Wpa,
        public bool $hidden = false,
    ) {
        $this->ssid = Text::clean($ssid, 'name of the network');
        $this->password = Text::clean($password, 'password of the network');

        if ('' === $this->ssid || \strlen($this->ssid) > 32) {
            throw InvalidContent::of('name of the network', 'from 1 to 32 bytes');
        }

        $hex = 1 === preg_match('/^[0-9A-Fa-f]+$/', $this->password);
        $length = mb_strlen($this->password, 'UTF-8');
        $valid = match ($security) {
            WifiSecurity::Wpa => ($length >= 8 && $length <= 63) || ($hex && 64 === $length),
            WifiSecurity::Wep => \in_array($length, [5, 13], true) || ($hex && \in_array($length, [10, 26], true)),
            WifiSecurity::Open => '' === $this->password,
        };

        if (!$valid) {
            throw InvalidContent::of('password of the network', match ($security) {
                WifiSecurity::Wpa => '8 to 63 characters, or 64 hexadecimal digits, for WPA', WifiSecurity::Wep => '5 or 13 characters, or 10 or 26 hexadecimal digits, for WEP', WifiSecurity::Open => 'empty for an open network',
            });
        }
    }

    #[Override]
    public function payload(): string
    {
        return 'WIFI:T:'.$this->security->value
            .';S:'.Text::field($this->ssid)
            .(WifiSecurity::Open === $this->security ? '' : ';P:'.Text::field($this->password))
            .($this->hidden ? ';H:true' : '')
            .';;';
    }

    #[Override]
    public function label(): string
    {
        return $this->ssid;
    }
}
