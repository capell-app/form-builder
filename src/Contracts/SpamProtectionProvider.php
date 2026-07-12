<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Contracts;

use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;

interface SpamProtectionProvider
{
    public function key(): string;

    /**
     * @param  array<string, mixed>  $input
     */
    public function verify(Form $form, array $input, SubmissionMetaData $meta): bool;
}
