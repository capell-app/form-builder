<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Support\Http\OutboundHttp;
use Capell\Core\Support\Http\OutboundHttpPolicy;
use Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver;
use Capell\FormBuilder\Data\ResolvedFormWebhookEndpointData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;
use Throwable;

final class DispatchSubmissionWebhookAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly FormBuilderWebhookHostResolver $hostResolver) {}

    /**
     * The per-form secret receivers use to verify `X-Capell-Signature`: HMAC-SHA256 of "<timestamp>.<raw body>".
     */
    public static function signingSecret(Form $form): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', 'capell-form-builder-webhook|' . (is_int($form->getKey()) || is_string($form->getKey()) ? (string) $form->getKey() : '') . '|' . (is_string($form->handle) ? $form->handle : ''), is_string($key) ? $key : '');
    }

    public function handle(Submission $submission): bool
    {
        $form = $submission->form;
        $settings = $form->settings;
        $url = is_string($settings?->webhookUrl) ? trim($settings->webhookUrl) : null;

        if ($url === null || $url === '') {
            return true;
        }

        try {
            $body = json_encode([
                'event' => 'form.submitted',
                'form' => [
                    'id' => $form->getKey(),
                    'handle' => $form->handle,
                    'name' => $form->name,
                    'site_id' => $form->site_id,
                ],
                'submission' => [
                    'id' => $submission->getKey(),
                    'status' => $submission->status->value,
                    'submitted_at' => $submission->submitted_at->toIso8601String(),
                    'payload' => $this->payloadWithoutStoragePaths($submission->payload->values ?? []),
                    'meta' => [
                        'url' => $submission->meta->url,
                        'referer' => $submission->meta->referer,
                    ],
                ],
            ], JSON_THROW_ON_ERROR);
            $timestamp = (string) now()->timestamp;

            $signatureHeaders = [
                'X-Capell-Timestamp' => $timestamp,
                'X-Capell-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, self::signingSecret($form)),
            ];

            $response = $this->allowsPrivateUrls()
                ? $this->sendPinnedToOperatorApprovedHost($url, $body, $signatureHeaders)
                : $this->sendGuarded($url, $body, $signatureHeaders);

            throw_unless($response->successful(), RuntimeException::class, sprintf('Submission webhook returned HTTP %d.', $response->status()));

            return true;
        } catch (Throwable $throwable) {
            Log::warning('Form Builder submission webhook dispatch failed.', [
                'form_id' => $form->getKey(),
                'submission_id' => $submission->getKey(),
                'exception' => $throwable::class,
                'message' => RedactSubmissionWebhookErrorMessageAction::run($throwable, $url),
            ]);

            return false;
        }
    }

    /**
     * Default path: Core's guarded client resolves the host through
     * ResolvesHostAddresses, refuses any non-public address, pins the
     * connection to the address it checked and never follows redirects.
     *
     * @param  array<string, string>  $signatureHeaders
     */
    private function sendGuarded(string $url, string $body, array $signatureHeaders): Response
    {
        $parts = $this->urlParts($url);

        throw_if($this->isPrivateHostLabel($parts['host']), InvalidArgumentException::class, 'Submission webhook URL host is not allowed.');

        return OutboundHttp::guarded(new OutboundHttpPolicy(
            schemes: $this->allowsInsecureUrls() ? ['https', 'http'] : ['https'],
            ports: [$parts['port']],
            timeoutSeconds: $this->timeoutSeconds(),
        ))
            ->withHeaders($signatureHeaders)
            ->withBody($body, 'application/json')
            ->post($url)
            ->throw();
    }

    /**
     * Operator opt-in path (`allow_private_urls`): the guarded client always
     * refuses private addresses, so internal targets keep the pinned request.
     *
     * @param  array<string, string>  $signatureHeaders
     */
    private function sendPinnedToOperatorApprovedHost(string $url, string $body, array $signatureHeaders): Response
    {
        $endpoint = $this->endpoint($url);

        return Http::timeout($this->timeoutSeconds())
            ->withoutRedirecting()
            ->withHeaders(['Host' => $endpoint->hostHeader(), ...$signatureHeaders])
            ->withOptions($this->requestOptions($endpoint))
            ->withBody($body, 'application/json')
            ->post($endpoint->url)
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function payloadWithoutStoragePaths(array $values): array
    {
        return array_map(static function (mixed $value): mixed {
            if (is_array($value) && array_key_exists('path', $value) && is_string($value['original_name'] ?? null)) {
                return array_intersect_key($value, array_flip(['original_name', 'mime_type', 'size']));
            }

            return $value;
        }, $values);
    }

    /**
     * @return array{scheme: string, host: string, port: int}
     */
    private function urlParts(string $url): array
    {
        $parts = parse_url($url);

        throw_if(! is_array($parts), InvalidArgumentException::class, 'Submission webhook URL must be an absolute HTTP URL.');

        $scheme = is_string($parts['scheme'] ?? null) ? strtolower($parts['scheme']) : null;
        $host = is_string($parts['host'] ?? null) ? strtolower($parts['host']) : null;

        throw_if(! in_array($scheme, ['https', 'http'], true) || $host === null || $host === '', InvalidArgumentException::class, 'Submission webhook URL must be an absolute HTTP URL.');
        throw_if($scheme !== 'https' && ! $this->allowsInsecureUrls(), InvalidArgumentException::class, 'Submission webhook URL must use HTTPS.');

        return ['scheme' => $scheme, 'host' => $host, 'port' => $this->port($parts, $scheme)];
    }

    private function endpoint(string $url): ResolvedFormWebhookEndpointData
    {
        $parts = $this->urlParts($url);
        $addresses = $this->resolvedHostAddresses($parts['host']);

        throw_if($addresses === [], InvalidArgumentException::class, 'Submission webhook URL host could not be resolved.');

        return new ResolvedFormWebhookEndpointData(
            url: $url,
            scheme: $parts['scheme'],
            host: $parts['host'],
            port: $parts['port'],
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

    private function isPrivateHostLabel(string $host): bool
    {
        return in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.localhost');
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
