<?php

declare(strict_types=1);

namespace Phil\QrCode\Content;

/**
 * The version of a vCard: 3.0 (RFC 2426), read by every phone, or 4.0 (RFC 6350).
 */
enum VCardVersion: string
{
    case V3 = '3.0';
    case V4 = '4.0';
}
