<?php

declare(strict_types=1);
use Illuminate\Support\Facades\File;

describe('form-builder capell.json manifest', function (): void {
    it('declares requires using full composer package names', function (): void {
        $manifest = json_decode(
            File::get(__DIR__ . '/../../capell.json'),
            associative: true,
        );

        $requires = $manifest['dependencies']['requires'] ?? [];

        foreach ($requires as $requirement) {
            expect($requirement)->toContain('/');
        }
    });

    it('requires the Capell packages it imports directly', function (): void {
        $manifest = json_decode(
            File::get(__DIR__ . '/../../capell.json'),
            associative: true,
        );

        expect($manifest['dependencies']['requires'])->toContain('capell-app/core')
            ->and($manifest['dependencies']['requires'])->toContain('capell-app/admin')
            ->and($manifest['dependencies']['requires'])->toContain('capell-app/frontend');
    });
});
