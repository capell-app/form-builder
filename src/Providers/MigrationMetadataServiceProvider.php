<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {

        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/form-builder', __DIR__ . '/../../database/migrations', [
                '2026_05_10_190849_01_create_form-builder_table' => 'forms',
                '2026_05_10_190849_02_create_submissions_table' => 'submissions',
            ]);
        });
    }
}
