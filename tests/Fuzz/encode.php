<?php

declare(strict_types=1);

/*
 * The encoder on texts nobody wrote: any bytes are either refused with DataTooLong or encoded, and the reader of the
 * tests reads back exactly the bytes given.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\DataTooLong;
use Phil\QrCode\QrCode;
use Phil\QrCode\Tests\Support\Decoder;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $code = QrCode::encode($input, ErrorCorrection::cases()[strlen($input) % 4]);
    } catch (DataTooLong) {
        return;
    }

    if (Decoder::text($code->rows()) !== $input) {
        throw new LogicException('The code does not read back as its text.');
    }
});
$config->setMaxLen(512);
