<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Route;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class CreateFormPaymentCheckoutRedirectUrlAction
{
    use AsFake;
    use AsObject;

    public function handle(Submission $submission): ?string
    {
        if (! IsFormPaymentIntegrationAvailableAction::run()) {
            return null;
        }

        if (! $this->hasPaymentField($submission)) {
            return null;
        }

        if (! Route::has('capell-payments.form-builder.checkout')) {
            return null;
        }

        $url = CreateFormPaymentCheckoutUrlAction::run($submission);

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function hasPaymentField(Submission $submission): bool
    {
        $submission->loadMissing('form');
        $form = $submission->form;

        if (! $form instanceof Form) {
            return false;
        }

        foreach ($form->schema ?? [] as $field) {
            $field = $field instanceof FormFieldData ? $field : FormFieldData::from($field);

            if ($field->type === FormFieldType::Payment) {
                return true;
            }
        }

        return false;
    }
}
