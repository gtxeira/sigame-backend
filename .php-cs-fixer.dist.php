<?php

declare(strict_types=1);

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        // Gerado pelo `cache:warmup`; o gerador do Symfony não emite declare(strict_types=1).
        'config/reference.php',
    ])
;

return new PhpCsFixer\Config()
    ->setRules([
        '@Symfony' => true,

        'declare_strict_types' => true,

        'array_syntax' => [
            'syntax' => 'short',
        ],

        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'blank_line_between_import_groups' => true,
        'no_unused_imports' => true,

        'trailing_comma_in_multiline' => [
            'elements' => [
                'arrays',
                'arguments',
                'parameters',
            ],
        ],

        'single_quote' => true,

        'yoda_style' => false,

        'fully_qualified_strict_types' => false,

        'no_superfluous_phpdoc_tags' => true,

        'single_line_throw' => false,

        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => false,
        ],

        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'do',
                'for',
                'foreach',
                'if',
                'return',
                'switch',
                'throw',
                'try',
                'while',
            ],
        ],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
