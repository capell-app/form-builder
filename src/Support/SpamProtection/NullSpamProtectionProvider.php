<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support\SpamProtection;

use Capell\FormBuilder\Contracts\SpamProtectionProvider;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;

final class NullSpamProtectionProvider implements SpamProtectionProvider
{
    public function key(): string
    {
        return 'none';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function verify(Form $form, array $input, SubmissionMetaData $meta): bool
    {
        return true;
    }
}
