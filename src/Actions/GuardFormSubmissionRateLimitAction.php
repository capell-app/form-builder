<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Support\FormSubmissionThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(Form $form, Collection<int, FormFieldData> $fields, array<string, mixed> $input, Request $request)
 */
final class GuardFormSubmissionRateLimitAction
{
    use AsFake;
    use AsObject;

    /**
     * Both limits at once, for callers that have already validated the input.
     *
     * @param  Collection<int, FormFieldData>  $fields
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Form $form, Collection $fields, array $input, Request $request): void
    {
        $this->guardAddress($form, $request);
        $this->guardRecipients($form, $fields, $input);
    }

    /**
     * The short per-address budget (12 a minute by default), spent by every final submission attempt, valid or not,
     * so flooding a form from one address is refused before any validation work.
     *
     * @throws ValidationException
     */
    public function guardAddress(Form $form, Request $request): void
    {
        if (! FormSubmissionThrottle::throttle()->hit($request, ['form' => $this->formDimension($form)])) {
            throw $this->tooMany();
        }
    }

    /**
     * The long per-recipient budget (3 an hour by default). It protects the people whose addresses go into the
     * form from repeated autoresponder mail, so only a submission that passed validation spends it: a visitor who
     * mistypes another field is told what to fix, not locked out for an hour.
     *
     * @param  Collection<int, FormFieldData>  $fields
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function guardRecipients(Form $form, Collection $fields, array $input): void
    {
        $limits = [];

        // Every address on the form counts: the autoresponder may mail any email field, so capping only one would
        // let a submitter rotate that field while pointing the mail at a victim in another.
        foreach ($this->emailAddresses($fields, $input) as $email) {
            $limits[$this->recipientRateLimitKey($form, $email)] = [
                $this->configuredThrottleValue('max_attempts_per_email', 3),
                $this->configuredThrottleValue('email_decay_seconds', 3600),
            ];
        }

        foreach ($limits as $key => [$maxAttempts, $decaySeconds]) {
            if (RateLimiter::hit($key, $decaySeconds) > $maxAttempts) {
                throw $this->tooMany();
            }
        }
    }

    private function tooMany(): ValidationException
    {
        return ValidationException::withMessages([
            'data' => __('capell-form-builder::message.too_many_submissions'),
        ]);
    }

    private function recipientRateLimitKey(Form $form, string $email): string
    {
        $siteId = $form->site_id;

        return 'capell-form-builder:submit-recipient:' . hash('sha256', (is_int($siteId) || is_string($siteId) ? (string) $siteId : '') . '|' . $email);
    }

    private function formDimension(Form $form): string
    {
        $formKey = $form->getKey();

        return implode('|', [
            is_int($formKey) || is_string($formKey) ? (string) $formKey : '',
            is_int($form->site_id) || is_string($form->site_id) ? (string) $form->site_id : '',
        ]);
    }

    /**
     * @param  Collection<int, FormFieldData>  $fields
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function emailAddresses(Collection $fields, array $input): array
    {
        $addresses = [];

        foreach ($fields as $field) {
            if ($field->type !== FormFieldType::Email) {
                continue;
            }

            $value = $input[$field->key] ?? null;
            $address = is_string($value) ? trim($value) : '';

            if (filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $addresses[] = $this->normaliseAddress($address);
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Collapses plus-tags and Gmail dots so aliases of one inbox share a budget.
     */
    private function normaliseAddress(string $address): string
    {
        [$local, $domain] = explode('@', Str::lower($address), 2) + [1 => ''];
        $local = explode('+', $local, 2)[0];

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local . '@' . $domain;
    }

    private function configuredThrottleValue(string $key, int $default): int
    {
        $value = config('capell-form-builder.throttle.' . $key);

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }
}
