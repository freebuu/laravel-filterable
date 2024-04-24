<?php

$finder = (new PhpCsFixer\Finder())
    ->name('*.php')
    ->in([
        __DIR__ . '/src'
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder);
