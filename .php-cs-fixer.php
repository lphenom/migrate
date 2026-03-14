<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new Config())
    ->setRules([
        '@PSR12'                            => true,
        'strict_param'                      => true,
        'declare_strict_types'              => true,
        'array_syntax'                      => ['syntax' => 'short'],
        'no_unused_imports'                 => true,
        'ordered_imports'                   => ['sort_algorithm' => 'alpha'],
        'single_quote'                      => true,
        'trailing_comma_in_multiline'       => ['elements' => ['arrays']],
        'no_trailing_whitespace'            => true,
        'blank_line_after_namespace'        => true,
        'method_argument_space'             => ['on_multiline' => 'ensure_fully_multiline'],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);

