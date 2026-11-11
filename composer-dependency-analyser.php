<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 *
 * GD is used by the production code but only required in development and suggested: PngWriter needs it only to draw
 * a logo over a PNG, and refuses the logo when it is missing.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnExtension('ext-gd', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
