<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class PruneExpiredFormSubmissionsAction
{
    use AsFake;
    use AsObject;

    public function handle(?int $retentionDays = null, bool $dryRun = false): int
    {
        $configuredDays = config('capell-form-builder.retention.days', 365);
        $days = max(1, $retentionDays ?? (is_numeric($configuredDays) ? (int) $configuredDays : 365));
        $query = Submission::query()
            ->where('legal_hold', false)
            ->where('submitted_at', '<', now()->subDays($days))
            ->where(static fn (Builder $query): Builder => $query->whereNull('retention_until')->orWhere('retention_until', '<=', now()));
        $count = (clone $query)->count();

        if ($dryRun) {
            return $count;
        }

        // A privacy erasure postponed by a hold that has since been released is finished here, whatever the age.
        ErasePendingHeldSubmissionsAction::run();

        // Each expired submission goes through the one delete routine, which re-checks the hold under a row lock and
        // keeps any submission whose uploads could not all be deleted.
        (clone $query)->select('id')->chunkById(200, static function ($chunk): void {
            foreach ($chunk->modelKeys() as $id) {
                if (is_numeric($id)) {
                    DeleteSubmissionAction::run((int) $id);
                }
            }
        });

        return $count;
    }
}
