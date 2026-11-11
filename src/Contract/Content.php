<?php

declare(strict_types=1);

namespace Phil\QrCode\Contract;

/**
 * What a QR code holds, written in the form phone readers act on: an address to open, a contact to save, a network
 * to join, an event to add, a place, a message to write, a number to call.
 */
interface Content
{
    /**
     * The text of the code, escaped as its format requires.
     */
    public function payload(): string;

    /**
     * A short text that says what the code holds, to show beside it or to name it for screen readers: the address,
     * the name, the network, the event; never a password.
     */
    public function label(): string;
}
