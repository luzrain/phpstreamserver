<?php

$rules = [
    // Rules that follow PSR-12 standard.
    '@PER-CS2.0' => true,

    // Rules that follow PSR-12 standard. This set contains rules that are risky.
    '@PER-CS2.0:risky' => true,

    // PHP arrays should be declared using the short syntax.
    'array_syntax' => ['syntax' => 'short'],

    // Each line of multi-line DocComments must have an asterisk [PSR-5] and must be aligned with the first one.
    'align_multiline_comment' => true,

    // A single space or none should be between cast and variable.
    'cast_spaces' => true,

    // There should not be any empty comments.
    'no_empty_comment' => true,

    // Unused use statements must be removed.
    'no_unused_imports' => true,

    // Scalar types should always be written in the same form. int not integer, bool not boolean, float not real or double.
    'phpdoc_scalar' => true,

    // Single line @var PHPDoc should have proper spacing.
    'phpdoc_single_line_var_spacing' => true,

    // Removes extra blank lines after summary and after description in PHPDoc.
    'phpdoc_trim' => true,

    // @var and @type annotations must have type and name in the correct order.
    'phpdoc_var_annotation_correct_order' => true,

    // Remove useless (semicolon) statements.
    'no_empty_statement' => true,

    // There MUST NOT be spaces around offset braces.
    'no_spaces_around_offset' => true,

    // Force strict types declaration in all files.
    'declare_strict_types' => true,

    // Comparisons should be strict.
    'strict_comparison' => true,

    // Ordering use statements
    'ordered_imports' => true,

    // Replace get_class calls on object variables with class keyword syntax.
    'get_class_to_class_keyword' => true,

    // Removes @param, @return and @var tags that don’t provide any useful information.
    'no_superfluous_phpdoc_tags' => true,

    // Multi-line arrays, arguments list, parameters list and match expressions must have a trailing comma.
    'trailing_comma_in_multiline' => [
        'after_heredoc' => true,
        'elements' => ['arrays', 'match', 'arguments', 'parameters'],
    ],

    // Empty body of class, interface, trait, enum or function must be abbreviated as {} and placed on the same line
    'single_line_empty_body' => false,

    // Class DateTimeImmutable should be used instead of DateTime.
    'date_time_immutable' => true,

    // Functions should be used with $strict param set to true.
    'strict_param' => true,

    // Method chaining MUST be properly indented.
    'method_chaining_indentation' => true,

    // Add leading \ before function invocation to speed up resolving.
    'native_function_invocation' => [
        'include' => ['@all'],
        'scope' => 'all',
        'strict' => true,
    ],
];

return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
    ->setFinder(PhpCsFixer\Finder::create()
        ->in(__DIR__ . '/src')
        ->in(__DIR__ . '/tests')
    )
    ->setRules($rules)
    ->setRiskyAllowed(true)
    ;
