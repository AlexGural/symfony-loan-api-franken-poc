<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->notPath('var')
    ->notPath('vendor')
;

$config = (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'single_line_throw' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
;

if (method_exists($config, 'setTargetPhpVersion')) {
    $config->setTargetPhpVersion('8.5');
} elseif (method_exists($config, 'setPhpVersion') && class_exists(PhpCsFixer\PhpVersion::class)) {
    if (method_exists(PhpCsFixer\PhpVersion::class, 'fromString')) {
        /** @psalm-suppress MixedMethodCall */
        $config->setPhpVersion(PhpCsFixer\PhpVersion::fromString('8.5'));
    }
}

return $config;
