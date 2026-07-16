<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
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

        $query->lazyById(200)->each(static function (Submission $submission): void {
            foreach (Arr::dot($submission->payload->values) as $key => $value) {
                if (! str_ends_with((string) $key, '.path') || ! is_string($value)) {
                    continue;
                }

                $prefix = str($key)->beforeLast('.path')->toString();
                $disk = data_get($submission->payload->values, $prefix . '.disk');

                if (is_string($disk) && $disk !== '') {
                    Storage::disk($disk)->delete($value);
                }
            }

            $submission->delete();
        });

        return $count;
    }
}
