<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\OrderedImportsFixer;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->append([__FILE__])
;

return (new PhpCsFixer\Config())
    ->setUsingCache(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_indentation' => true,
        'declare_strict_types' => true,
        'get_class_to_class_keyword' => true,
        'heredoc_to_nowdoc' => true,
        'list_syntax' => ['syntax' => 'short'],
        'modernize_strpos' => true,
        'no_null_property_initialization' => true,
        'no_superfluous_phpdoc_tags' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'ordered_imports' => [
            'imports_order' => [
                OrderedImportsFixer::IMPORT_TYPE_CLASS,
                OrderedImportsFixer::IMPORT_TYPE_CONST,
                OrderedImportsFixer::IMPORT_TYPE_FUNCTION,
            ],
        ],
        'single_line_throw' => false,
        'ternary_to_null_coalescing' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
