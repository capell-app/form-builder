<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Jobs;

use Capell\FormBuilder\Actions\DispatchSubmissionWebhookAction;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class DispatchSubmissionWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly int $submissionId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function uniqueId(): string
    {
        return (string) $this->submissionId;
    }

    public function handle(): void
    {
        $submission = Submission::query()->find($this->submissionId);

        if (! $submission instanceof Submission) {
            return;
        }

        $dispatched = DispatchSubmissionWebhookAction::run($submission);

        throw_if(! $dispatched, RuntimeException::class, 'Form Builder submission webhook delivery failed.');
    }

    public function failed(?Throwable $throwable): void
    {
        Log::error('Form Builder submission webhook job exhausted its retries.', [
            'submission_id' => $this->submissionId,
            'exception' => $throwable instanceof Throwable ? $throwable::class : null,
        ]);
    }
}
