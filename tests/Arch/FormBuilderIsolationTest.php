<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('keeps form-builder package references inside the form-builder source package', function (): void {
    $rootPath = dirname(__DIR__, 4);
    $violations = [];

    $files = (new Finder)
        ->files()
        ->in($rootPath . '/packages')
        ->path('/\/src\//')
        ->name('*.php')
        ->contains('Capell\\FormBuilder');

    foreach ($files as $file) {
        $relativePath = str_replace($rootPath . '/', '', $file->getPathname());

        if (str_starts_with($relativePath, 'packages/form-builder/src/')) {
            continue;
        }

        if (declaresFormBuilderDependency($rootPath, $relativePath)) {
            continue;
        }

        $violations[] = $relativePath;
    }

    expect($violations)->toBeEmpty();
});

arch()
    ->expect('Capell\FormBuilder')
    ->classes()
    ->toUseStrictEquality();

function declaresFormBuilderDependency(string $rootPath, string $relativePath): bool
{
    if (! preg_match('#^packages/([^/]+)/src/#', $relativePath, $matches)) {
        return false;
    }

    $composerPath = $rootPath . '/packages/' . $matches[1] . '/composer.json';
    if (! is_file($composerPath)) {
        return false;
    }

    $composer = json_decode((string) file_get_contents($composerPath), true);
    if (! is_array($composer)) {
        return false;
    }

    $requires = [
        ...(is_array($composer['require'] ?? null) ? $composer['require'] : []),
        ...(is_array($composer['suggest'] ?? null) ? $composer['suggest'] : []),
    ];

    return array_key_exists('capell-app/form-builder', $requires);
}
