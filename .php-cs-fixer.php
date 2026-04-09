<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'storage', 'bootstrap/cache', 'public/build'])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return new Config()
    ->setRiskyAllowed(true)
    ->setRules([
        // PSR Standards
        '@PSR12' => true,
        '@PSR12:risky' => true,

        // PHP 8.4 Migration
        '@PHP84Migration' => true,

        // Array notation
        'array_syntax' => ['syntax' => 'short'],

        // Import ordering
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'no_unused_imports' => true,

        // Whitespace
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'try'],
        ],
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'throw', 'use'],
        ],

        // Code quality
        'no_useless_else' => true,
        'no_useless_return' => true,
        'single_quote' => true,
        'concat_space' => ['spacing' => 'one'],

        // Strict types
        'declare_strict_types' => true,
        'strict_param' => true,

        // Native function invocation (prefijo \ para funciones globales)
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope' => 'namespaced',
            'strict' => true,
        ],

        // Final classes when possible
        'final_class' => true,
        'final_internal_class' => true,

        // Visibility
        'visibility_required' => [
            'elements' => ['property', 'method', 'const'],
        ],

        // Return types
        'return_type_declaration' => ['space_before' => 'none'],

        // Binary operators
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],

        // Cast
        'cast_spaces' => ['space' => 'single'],

        // Control structures
        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,
    ])
    ->setFinder($finder);
