<?php

$finder = (new PhpCsFixer\Finder())->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced', 'strict' => true],
        'header_comment' => false,
    ])
    ->setFinder($finder);
