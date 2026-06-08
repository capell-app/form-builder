<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver;
use Capell\FormBuilder\Data\ResolvedFormWebhookEndpointData;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class DispatchSubmissionWebhookAction
{
    use AsAction;

    public function __construct(private readonly FormBuilderWebhookHostResolver $hostResolver) {}

    public function handle(Submission $submission): void
    {
        $form = $submission->form;
        $url = is_string($form?->settings?->webhookUrl) ? trim($form->settings->webhookUrl) : null;

        if ($url === null || $url === '') {
            return;
        }

        try {
            $endpoint = $this->endpoint($url);

            Http::timeout($this->timeoutSeconds())
                ->withoutRedirecting()
                ->withHeaders(['Host' => $endpoint->hostHeader()])
                ->withOptions($this->requestOptions($endpoint))
                ->post($endpoint->url, [
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
                        'submitted_at' => $submission->submitted_at?->toIso8601String(),
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

    private function endpoint(string $url): ResolvedFormWebhookEndpointData
    {
        $parts = parse_url($url);

        throw_if(! is_array($parts), InvalidArgumentException::class, 'Submission webhook URL must be an absolute HTTP URL.');

        $scheme = is_string($parts['scheme'] ?? null) ? strtolower($parts['scheme']) : null;
        $host = is_string($parts['host'] ?? null) ? strtolower($parts['host']) : null;

        throw_if(! in_array($scheme, ['https', 'http'], true) || $host === null || $host === '', InvalidArgumentException::class, 'Submission webhook URL must be an absolute HTTP URL.');
        throw_if($scheme !== 'https' && ! $this->allowsInsecureUrls(), InvalidArgumentException::class, 'Submission webhook URL must use HTTPS.');

        $addresses = $this->resolvedHostAddresses($host);

        throw_if($addresses === [], InvalidArgumentException::class, 'Submission webhook URL host could not be resolved.');
        throw_if(! $this->allowsPrivateUrls() && $this->hasPrivateAddress($addresses), InvalidArgumentException::class, 'Submission webhook URL host is not allowed.');

        return new ResolvedFormWebhookEndpointData(
            url: $url,
            scheme: $scheme,
            host: $host,
            port: $this->port($parts, $scheme),
            address: $addresses[0],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestOptions(ResolvedFormWebhookEndpointData $endpoint): array
    {
        throw_unless(defined('CURLOPT_RESOLVE'), InvalidArgumentException::class, 'Submission webhook dispatch requires cURL host pinning support.');

        return [
            'curl' => [
                CURLOPT_RESOLVE => [$endpoint->curlResolveEntry()],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function port(array $parts, string $scheme): int
    {
        $port = $parts['port'] ?? null;

        if (is_int($port) && $port > 0 && $port <= 65535) {
            return $port;
        }

        return $scheme === 'https' ? 443 : 80;
    }

    /**
     * @param  list<string>  $addresses
     */
    private function hasPrivateAddress(array $addresses): bool
    {
        foreach ($addresses as $address) {
            if ($this->isPrivateAddress($address)) {
                return true;
            }
        }

        return false;
    }

    private function isPrivateHostLabel(string $host): bool
    {
        return in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.localhost');
    }

    private function isPrivateAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    /**
     * @return list<string>
     */
    private function resolvedHostAddresses(string $host): array
    {
        if ($this->isPrivateHostLabel($host)) {
            return ['127.0.0.1'];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = $this->hostResolver->resolve($host);

        return array_values(collect($addresses)
            ->filter(static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP) !== false)
            ->unique()
            ->values()
            ->all());
    }

    private function allowsInsecureUrls(): bool
    {
        return (bool) config('capell-form-builder.webhooks.allow_insecure_urls', false);
    }

    private function allowsPrivateUrls(): bool
    {
        return (bool) config('capell-form-builder.webhooks.allow_private_urls', false);
    }

    private function timeoutSeconds(): int
    {
        $timeout = config('capell-form-builder.webhooks.timeout_seconds', 10);

        return is_numeric($timeout) ? max(1, min((int) $timeout, 60)) : 10;
    }
}
