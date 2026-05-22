<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP83Migration'                    => true,
        '@PSR12'                             => true,
        '@PSR12:risky'                       => true,
        'concat_space'                       => ['spacing' => 'one'],
        'declare_strict_types'               => true,
        'global_namespace_import'            => ['import_classes' => false],
        'no_unused_imports'                  => true,
        'no_superfluous_phpdoc_tags'         => ['remove_inheritdoc' => true],
        'ordered_imports'                    => ['sort_algorithm' => 'alpha'],
        'phpdoc_align'                       => ['align' => 'left'],
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
        'single_quote'                       => true,
        'trailing_comma_in_multiline'        => ['elements' => ['arrays', 'arguments', 'parameters']],
    ])
    ->setFinder($finder);
