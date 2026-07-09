<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @method static void run(Form $form, Collection $fields, array<string, mixed> $input = [], ?string $ipAddress = null)
 */
final class GuardFormSubmissionRateLimitAction
{
    use AsAction;

    /**
     * @param  Collection<int, FormFieldData>  $fields
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Form $form, Collection $fields, array $input = [], ?string $ipAddress = null): void
    {
        $key = $this->rateLimitKey($form, $fields, $input, $ipAddress);
        $maxAttempts = $this->configuredThrottleValue('max_attempts', 12);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'data' => __('capell-form-builder::message.too_many_submissions'),
            ]);
        }

        RateLimiter::hit($key, $this->configuredThrottleValue('decay_seconds', 60));
    }

    /**
     * @param  Collection<int, FormFieldData>  $fields
     * @param  array<string, mixed>  $input
     */
    private function rateLimitKey(Form $form, Collection $fields, array $input, ?string $ipAddress): string
    {
        return 'capell-form-builder:submit:' . hash('sha256', implode('|', [
            (string) $form->getKey(),
            (string) $form->site_id,
            $this->emailDimension($fields, $input),
            (string) $ipAddress,
        ]));
    }

    /**
     * @param  Collection<int, FormFieldData>  $fields
     * @param  array<string, mixed>  $input
     */
    private function emailDimension(Collection $fields, array $input): string
    {
        $emailField = $fields->first(static fn (FormFieldData $field): bool => $field->type === FormFieldType::Email);

        if (! $emailField instanceof FormFieldData) {
            return '';
        }

        $email = $input[$emailField->key] ?? null;

        return is_scalar($email) ? Str::lower((string) $email) : '';
    }

    private function configuredThrottleValue(string $key, int $default): int
    {
        $value = config('capell-form-builder.throttle.' . $key);

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }
}
