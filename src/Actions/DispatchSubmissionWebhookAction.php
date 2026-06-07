<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class DispatchSubmissionWebhookAction
{
    use AsAction;

    public function handle(Submission $submission): void
    {
        $form = $submission->form;
        $url = $form?->settings?->webhookUrl;

        if (! is_string($url) || trim($url) === '') {
            return;
        }

        try {
            Http::timeout($this->timeoutSeconds())->post($url, [
                'event' => 'form.submitted',
                'form' => [
                    'id' => $form?->getKey(),
                    'handle' => $form?->handle,
                    'name' => $form?->name,
                    'site_id' => $form?->site_id,
                ],
                'submission' => [
                    'id' => $submission->getKey(),
                    'status' => $submission->status?->value,
                    'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
                    'payload' => $submission->payload->values ?? [],
                    'meta' => [
                        'url' => $submission->meta?->url,
                        'referer' => $submission->meta?->referer,
                    ],
                ],
            ])->throw();
        } catch (Throwable $throwable) {
            Log::warning('Form Builder submission webhook dispatch failed.', [
                'form_id' => $form?->getKey(),
                'submission_id' => $submission->getKey(),
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function timeoutSeconds(): int
    {
        $timeout = config('capell-form-builder.webhooks.timeout_seconds', 10);

        return is_numeric($timeout) ? max(1, min((int) $timeout, 60)) : 10;
    }
}
