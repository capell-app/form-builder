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

        // Chunked, re-checking legal hold per chunk, so a hold applied mid-run keeps both the row and its files.
        (clone $query)->select('id')->chunkById(200, static function ($chunk): void {
            $ids = $chunk->modelKeys();
            $expired = static fn (): Builder => Submission::query()->whereKey($ids)->where('legal_hold', false);

            DeleteSubmissionUploadsAction::run($expired());
            $expired()->delete();
        });

        return $count;
    }
}
