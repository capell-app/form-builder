<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Submission;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveSubmissionReplyAddressAction
{
    use AsAction;

    public function handle(Submission $submission): ?string
    {
        if ($submission->status === SubmissionStatus::Spam) {
            return null;
        }

        $submission->loadMissing('form');

        $values = $submission->payload->values ?? [];

        foreach ($submission->form->schema ?? [] as $field) {
            if (is_array($field)) {
                $field = FormFieldData::from($field);
            }

            if ($field->type !== FormFieldType::Email) {
                continue;
            }

            $address = $this->validatedAddress($values[$field->key] ?? null);

            if ($address !== null) {
                return $address;
            }
        }

        return null;
    }

    private function validatedAddress(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $address = trim($value);

        return filter_var($address, FILTER_VALIDATE_EMAIL) === false ? null : $address;
    }
}
