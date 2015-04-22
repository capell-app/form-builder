<?php

declare(strict_types=1);

use Capell\FormBuilder\Jobs\DispatchSubmissionWebhookJob;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Http;

it('retries webhook deliveries unless the endpoint returns a successful status', function (int $status): void {
    Http::fake(['*' => Http::response('', $status, ['Location' => 'https://127.0.0.1/private'])]);
    $form = Form::factory()->create(['settings' => ['webhook_url' => 'https://8.8.8.8/form']]);
    $submission = Submission::factory()->for($form)->create();
    $job = new DispatchSubmissionWebhookJob((int) $submission->getKey());

    if ($status >= 200 && $status < 300) {
        $job->handle();
    } else {
        expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'webhook delivery failed');
    }

    Http::assertSentCount(1);
    expect($submission->fresh())->not->toBeNull();
})->with([200, 204, 302, 304, 307, 400, 500]);

it('retries webhook connection failures without losing the submission', function (): void {
    Http::fake(['*' => Http::failedConnection()]);
    $form = Form::factory()->create(['settings' => ['webhook_url' => 'https://8.8.8.8/form']]);
    $submission = Submission::factory()->for($form)->create();

    expect(fn () => new DispatchSubmissionWebhookJob((int) $submission->getKey())->handle())
        ->toThrow(RuntimeException::class, 'webhook delivery failed');
    expect($submission->fresh())->not->toBeNull();
});
