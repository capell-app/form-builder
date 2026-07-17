<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Console\Commands;

use Capell\FormBuilder\Actions\PruneExpiredFormSubmissionsAction;
use Illuminate\Console\Command;

final class PruneExpiredFormSubmissionsCommand extends Command
{
    protected $signature = 'capell:form-builder:prune {--days=} {--dry-run}';

    protected $description = 'Prune expired form submissions unless protected by a legal hold';

    public function handle(): int
    {
        $days = $this->option('days');
        $count = PruneExpiredFormSubmissionsAction::run(
            is_numeric($days) ? max(1, (int) $days) : null,
            (bool) $this->option('dry-run'),
        );
        $this->info("Matched {$count} expired form submissions.");

        return self::SUCCESS;
    }
}
