<?php

declare(strict_types=1);

namespace Phil\QrCode\Tests\Support;

/**
 * Reads back the codes the typed contents write, as a phone reader would: the fields of a Wi-Fi code or a MeCard,
 * unescaped, and the lines of a vCard or an event, unfolded and unescaped.
 */
final class Fields
{
    /**
     * The fields of `WIFI:…;;` or `MECARD:…;;`, by name, each list of values unescaped.
     *
     * @return array<string, list<string>>
     */
    public static function semicolons(string $payload): array
    {
        $body = substr($payload, (int) strpos($payload, ':') + 1);
        $fields = [];
        $name = '';
        $value = '';
        $inName = true;

        for ($i = 0, $length = \strlen($body); $i < $length; ++$i) {
            $c = $body[$i];

            if ('\\' === $c && $i + 1 < $length) {
                $value .= $body[++$i];
            } elseif ($inName && ':' === $c) {
                $inName = false;
            } elseif ($inName) {
                $name .= $c;
            } elseif (';' === $c) {
                $fields[$name][] = $value;
                $name = '';
                $value = '';
                $inName = true;
            } else {
                $value .= $c;
            }
        }

        return $fields;
    }

    /**
     * The properties of a vCard or an event, unfolded, each value unescaped, the parameters kept in the name.
     *
     * @return list<array{string, string}>
     */
    public static function lines(string $payload): array
    {
        $properties = [];

        foreach (explode("\r\n", str_replace("\r\n ", '', $payload)) as $line) {
            [$name, $value] = explode(':', $line, 2) + [1 => ''];
            $properties[] = [$name, (string) preg_replace_callback('/\\\\(.)/', static fn (array $m): string => 'n' === $m[1] || 'N' === $m[1] ? "\n" : $m[1], $value)];
        }

        return $properties;
    }
}
