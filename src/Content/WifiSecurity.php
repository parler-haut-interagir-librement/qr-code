<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

/**
 * How a Wi-Fi network is protected, as the `T:` field of a Wi-Fi code writes it: `WPA` for WPA, WPA2 and WPA3
 * personal, `WEP`, `nopass` for an open network.
 */
enum WifiSecurity: string
{
    case Wpa = 'WPA';
    case Wep = 'WEP';
    case Open = 'nopass';
}
