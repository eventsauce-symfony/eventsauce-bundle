<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__,
    ])
    ->exclude(['vendor', 'tools'])
;

return (new Config())
    ->setRules([
        '@Symfony' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'no_alias_functions' => true,
        'global_namespace_import' => ['import_classes' => true],
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['const', 'class', 'function'],
        ],
        'declare_strict_types' => true,
        'yoda_style' => true,
        'date_time_immutable' => true,
        'no_blank_lines_after_class_opening' => true,
        'use_arrow_functions' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'binary_operator_spaces' => true,
        'void_return' => true,
        'return_type_declaration' => true,
        'trailing_comma_in_multiline' => true,
        'class_attributes_separation' => ['elements' => ['property' => 'one', 'method' => 'one']],
    ])
    ->setFinder($finder)
;