<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Models\Form;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<string, string> run(Form $form, array<string, mixed> $input = [], string $prefix = '')
 */
final class BuildFormValidationAttributesAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function handle(Form $form, array $input = [], string $prefix = ''): array
    {
        return ResolveVisibleFormFieldsAction::run($form, $input)
            ->mapWithKeys(static fn (FormFieldData $field): array => [$prefix . $field->key => $field->label])
            ->all();
    }
}
