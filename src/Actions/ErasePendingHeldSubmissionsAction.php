<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Finishes privacy erasures that a legal hold postponed: deletes, through DeleteSubmissionAction, each submission
 * that has an erasure pending and is no longer held. DeleteSubmissionAction announces each one it deletes, so the privacy
 * request that kept it can record that its erasure is complete.
 *
 * @method static list<int> run(?Builder<Submission> $scope = null)
 */
final class ErasePendingHeldSubmissionsAction
{
    use AsFake;
    use AsObject;

    /** Payload: contributor key, retention reason, erased record ids. */
    public const string RETAINED_RECORDS_ERASED_EVENT = DeleteSubmissionAction::RETAINED_RECORDS_ERASED_EVENT;

    /**
     * @param  Builder<Submission>|null  $scope  limits the sweep, for example to one submission
     * @return list<int> the erased submission ids
     */
    public function handle(?Builder $scope = null): array
    {
        $pending = static fn (): Builder => ($scope !== null ? clone $scope : Submission::query())
            ->whereNotNull('erasure_requested_at')
            ->where('legal_hold', false);

        $erased = [];

        $pending()->select('id')->chunkById(200, static function ($chunk) use (&$erased): void {
            foreach ($chunk->modelKeys() as $id) {
                if (is_numeric($id) && DeleteSubmissionAction::run((int) $id)) {
                    $erased[] = (int) $id;
                }
            }
        });

        // DeleteSubmissionAction announces each erasure, whichever route deleted it.
        return $erased;
    }
}
