<?php

declare(strict_types=1);

use Capell\Core\Contracts\Security\ResolvesHostAddresses;
use Capell\FormBuilder\Actions\DispatchSubmissionWebhookAction;
use Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Tests\Fixtures\StaticFormBuilderWebhookHostResolver;
use Capell\FormBuilder\Tests\Fixtures\StaticHostAddressResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function webhookSubmissionFor(string $webhookUrl): Submission
{
    $form = Form::factory()->create(['settings' => ['webhook_url' => $webhookUrl]]);

    return Submission::factory()->for($form)->create();
}

it('sends nothing when the webhook host resolves through Core to an internal address', function (string $address): void {
    app()->instance(ResolvesHostAddresses::class, new StaticHostAddressResolver(['hooks.example.test' => [$address]]));
    app()->instance(FormBuilderWebhookHostResolver::class, new StaticFormBuilderWebhookHostResolver(['hooks.example.test' => ['93.184.216.34']]));
    Http::fake(['*' => Http::response(['ok' => true])]);

    $delivered = DispatchSubmissionWebhookAction::run(webhookSubmissionFor('https://hooks.example.test/form'));

    expect($delivered)->toBeFalse();
    Http::assertNothingSent();
})->with([
    'loopback' => '127.0.0.1',
    'cloud metadata service' => '169.254.169.254',
]);

it('sends a signed webhook when the host resolves through Core to a public address', function (): void {
    app()->instance(ResolvesHostAddresses::class, new StaticHostAddressResolver(['hooks.example.test' => ['93.184.216.34']]));
    Http::fake(['*' => Http::response(['ok' => true])]);

    $submission = webhookSubmissionFor('https://hooks.example.test/form');
    $delivered = DispatchSubmissionWebhookAction::run($submission);

    expect($delivered)->toBeTrue();
    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($submission): bool {
        $timestamp = $request->header('X-Capell-Timestamp')[0] ?? '';
        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $request->body(), DispatchSubmissionWebhookAction::signingSecret($submission->form));

        return $request->url() === 'https://hooks.example.test/form'
            && $request->hasHeader('X-Capell-Signature', $expected)
            && $request['event'] === 'form.submitted';
    });
});

it('still sends to an internal address when the operator allows private webhook URLs', function (): void {
    config()->set('capell-form-builder.webhooks.allow_private_urls', true);
    app()->instance(ResolvesHostAddresses::class, new StaticHostAddressResolver(['hooks.internal.test' => ['10.0.0.5']]));
    app()->instance(FormBuilderWebhookHostResolver::class, new StaticFormBuilderWebhookHostResolver(['hooks.internal.test' => ['10.0.0.5']]));

    /** @var array<string, mixed>|null $requestOptions */
    $requestOptions = null;
    Http::fake(function (Request $request, array $options) use (&$requestOptions) {
        $requestOptions = $options;

        return Http::response(['ok' => true]);
    });

    $delivered = DispatchSubmissionWebhookAction::run(webhookSubmissionFor('https://hooks.internal.test/form'));

    expect($delivered)->toBeTrue()
        ->and(data_get($requestOptions, 'curl.' . CURLOPT_RESOLVE))->toBe(['hooks.internal.test:443:10.0.0.5']);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Capell-Signature'));
});
