<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
$config
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_extra_blank_lines' => true,
        'no_spaces_around_offset' => true,
        'no_spaces_inside_parenthesis' => true,
        'no_trailing_whitespace' => true,
        'single_blank_line_at_eof' => true,
        'phpdoc_separation' => ['groups' => [['ORM\\*'], ['Assert\\*']]],
    ])
    ->setFinder($finder)
;

return $config;
