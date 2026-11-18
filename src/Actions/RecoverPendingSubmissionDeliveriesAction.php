<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Retries notification and autoresponder mail left pending by a failed or interrupted submission.
 *
 * Only submissions pending for longer than the grace period are retried, so a request still
 * delivering its own mail is never raced.
 *
 * @method static int run(int $graceMinutes = 5)
 */
final class RecoverPendingSubmissionDeliveriesAction
{
    use AsFake;
    use AsObject;

    /** Returns how many pending submissions are now delivered. */
    public function handle(int $graceMinutes = 5): int
    {
        $recovered = 0;

        Submission::query()
            ->whereNotNull('delivery_pending_at')
            ->where('delivery_pending_at', '<=', now()->subMinutes(max(0, $graceMinutes)))
            ->lazyById(100)
            ->each(static function (Submission $submission) use (&$recovered): void {
                if (DeliverSubmissionMailAction::run($submission)) {
                    $recovered++;
                }
            });

        return $recovered;
    }
}
