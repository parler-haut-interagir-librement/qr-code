<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The typed contents on fields nobody wrote, and the encoder on any bytes, read back by the reader of the tests.
        'targets' => [
            'contents' => 'tests/Fuzz/contents.php',
            'encode' => 'tests/Fuzz/encode.php',
        ],
        'corpus' => 'tests/Fuzz/corpus',
    ],
    'infection' => [
        'mutators' => [
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // Every version's step is the same from the last row less 6 or less 7: ceil() of half the span over
                    // the intervals, made even, absorbs one row (checked for versions 2 to 40).
                    '\$step = 32 === \$version \? 26 : .*',
                    // BCH codes read one bit above the value's width: that bit is zero, so it changes nothing.
                    '\$bits = self::bch\(\$level->formatBits\(\) << 3 \| \$mask, 5, .*',
                    '\$bits = self::bch\(\$this->version, 6, .*',
                    // A finder-like pattern never overlaps itself one module later: the search may resume two modules on.
                    'for \(\$at = strpos\(\$line, \$pattern\); .*',
                    // The ninth row and column around a finder hold the format information, drawn over them afterwards.
                    'for \(\$[xy] = max\(0, \$c[xy] - 4\); .*',
                    // array_shift() renumbers the remainder at each codeword, whatever its first key.
                    '\$remainder = array_fill\(0, \$degree, 0\);',
                    // A zone has a module and a module a pixel: the canvas of a logo is never smaller than 1 × 1.
                    '\$canvas = imagecreatetruecolor\(max\(1, \$side\), max\(1, \$side\)\);',
                    // The canvas of a logo is blank when it is filled: any of its pixels fills all of it.
                    'imagefill\(\$canvas, 0, 0, .*',
                    // The first pixel of the logo is its own margin, light or transparent alike once scaled.
                    'imagecopyresampled\(\$canvas, \$image, intdiv\(\$side - \$w, 2\), intdiv\(\$side - \$h, 2\), 0, 0, .*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // array_shift() renumbers the remainder at each codeword, whatever its first key.
                    '\$remainder = array_fill\(0, \$degree, 0\);',
                    // The first mask always scores below PHP_INT_MAX, so the initial choice is never kept.
                    '\$best = 0;',
                    // Mask -1 draws as mask 7, the last of the loop; its format information only makes it score apart.
                    'for \(\$mask = 0; \$mask < 8; \+\+\$mask\) \{',
                    // A block has no codeword -1: isset() skips it.
                    'for \(\$i = 0; \$i <= \$short; \+\+\$i\) \{',
                    // BCH codes may start one bit higher: the bits above the value's width are zero.
                    'for \(\$bit = \$degree \+ \$width - 1; .*',
                    '\$canvas = imagecreatetruecolor\(max\(1, \$side\), max\(1, \$side\)\);',
                    'imagecopyresampled\(\$canvas, \$image, intdiv\(\$side - \$w, 2\), intdiv\(\$side - \$h, 2\), 0, 0, .*',
                ],
            ],
            // Every zone too close to the finders also loses more codewords than its blocks may: it is refused twice
            // over (checked for versions 1 to 3, where such zones exist, at every level and share).
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => ['return null; \/\/ too close to the finders'],
            ],
            // bindec() of eight bits is an int; and the share of a side, rounded up, is a whole float that `|`
            // takes as the int it is.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'return implode\(\'\', array_map\(static fn \(string \$byte\): string => \\\\chr\(\(int\) bindec.*',
                    '\$side = \(\(int\) ceil\(\$share \* \$size\)\) \| 1;',
                ],
            ],
            'Minus' => [
                'ignoreSourceCodeByRegex' => [
                    'for \(\$bit = \$degree \+ \$width - 1; .*',
                ],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    // Mask 8 draws as mask 7 and scores the same, so it is never chosen over it.
                    'for \(\$mask = 0; \$mask < 8; \+\+\$mask\) \{',
                    // The first row of the canvas of a logo is its light margin, as light as the zone it covers.
                    'if \(\$py < 0 \|\| \$py >= \$width\) \{',
                ],
            ],
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    // A sum and a difference have the same parity: the masks that test parity cannot tell them apart.
                    '0 => 0 === \(\$x \+ \$y\) % 2,',
                    '4 => 0 === \(intdiv\(\$x, 3\) \+ intdiv\(\$y, 2\)\) % 2,',
                    '6 => 0 === \(\$x \* \$y % 2 \+ \$x \* \$y % 3\) % 2,',
                    'default => 0 === \(\(\$x \+ \$y\) % 2 \+ \$x \* \$y % 3\) % 2,',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
