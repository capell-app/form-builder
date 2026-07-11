<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Spatie\LaravelData\Data;

final class FormPaymentCheckoutData extends Data
{
    public function __construct(
        public Form $form,
        public Submission $submission,
        public FormFieldData $field,
        public int $amountCents,
        public string $currency,
        public string $successUrl,
        public string $cancelUrl,
        public ?string $customerEmail = null,
        public ?string $customerName = null,
    ) {}
}
