<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support\SpamProtection;

use Capell\FormBuilder\Contracts\SpamProtectionProvider;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Facades\Http;

final class TurnstileSpamProtectionProvider implements SpamProtectionProvider
{
    public function key(): string
    {
        return 'turnstile';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function verify(Form $form, array $input, SubmissionMetaData $meta): bool
    {
        $secret = config('capell-form-builder.spam_protection.turnstile.secret_key');
        $token = $input[$this->tokenField()] ?? null;

        if (! is_string($secret) || trim($secret) === '' || ! is_string($token) || trim($token) === '') {
            return false;
        }

        $response = Http::asForm()
            ->timeout($this->timeoutSeconds())
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $meta->ipAddress,
            ]);

        if (! $response->ok()) {
            return false;
        }

        return $response->json('success') === true;
    }

    private function tokenField(): string
    {
        $field = config('capell-form-builder.spam_protection.token_field', 'cf-turnstile-response');

        return is_string($field) && trim($field) !== '' ? $field : 'cf-turnstile-response';
    }

    private function timeoutSeconds(): int
    {
        $timeout = config('capell-form-builder.spam_protection.timeout_seconds', 5);

        return is_numeric($timeout) ? max(1, min(30, (int) $timeout)) : 5;
    }
}
