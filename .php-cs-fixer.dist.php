<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in(
        [
            __DIR__ . DIRECTORY_SEPARATOR . 'bin',
            __DIR__ . DIRECTORY_SEPARATOR . 'src',
        ],
    )
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Base standard PER-CS 3.0 (includes PSR-12)
        // @see https://www.php-fig.org/per/coding-style/
        '@PER-CS3x0:risky' => true,

        // Remove unused imports/use statements
        'no_unused_imports' => true,

        // Sort imports alphabetically
        'ordered_imports' => ['sort_algorithm' => 'alpha'],

        // Trailing commas in multiline arrays, arguments, parameters and match (PHP 8.2 style)
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],
    ])
    ->setFinder($finder);
