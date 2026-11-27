<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Places or releases a submission's legal hold, as the caller asked. It never flips the current state, so two
 * operators placing a hold at once both leave it held.
 *
 * The decision is made on the locked, current row, and a row already in the requested state is left alone. A privacy
 * erasure keeps a held submission and marks it as awaiting erasure. Releasing that hold erases it, so the release also
 * needs the delete ability.
 *
 * @method static bool run(Submission $submission, bool $held, ?Authenticatable $actor = null)
 */
final class SetSubmissionLegalHoldAction
{
    use AsFake;
    use AsObject;

    /** Returns true when releasing the hold erased the submission. */
    public function handle(Submission $submission, bool $held, ?Authenticatable $actor = null): bool
    {
        $gate = $actor instanceof Authenticatable ? Gate::forUser($actor) : app(GateContract::class);

        // The hold change commits on its own. Erasing afterwards, outside this transaction, means a file deletion that
        // fails part-way can never roll the hold back on over data already deleted: the submission stays released and
        // marked, and the prune sweep finishes it.
        $erases = DB::transaction(static function () use ($submission, $held, $gate): bool {
            $current = Submission::query()->whereKey($submission->getKey())->lockForUpdate()->firstOrFail();
            $gate->authorize('update', $current);

            if ($current->legal_hold === $held) {
                $submission->setRawAttributes($current->getAttributes(), true);

                return false;
            }

            $erases = ! $held && $current->erasure_requested_at !== null;

            if ($erases) {
                $gate->authorize('delete', (clone $current)->forceFill(['legal_hold' => false]));
            }

            $current->forceFill(['legal_hold' => $held])->save();
            $submission->setRawAttributes($current->getAttributes(), true);

            return $erases;
        });

        return $erases && ErasePendingHeldSubmissionsAction::run(Submission::query()->whereKey($submission->getKey())) !== [];
    }
}
