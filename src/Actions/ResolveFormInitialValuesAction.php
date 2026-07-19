<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\LaravelData\DataCollection;

/**
 * @method static array<string, bool|float|int|string|null> run(Form $form, array<string, mixed> $values)
 */
final class ResolveFormInitialValuesAction
{
    use AsFake;
    use AsObject;

    private const int MAXIMUM_STRING_LENGTH = 2000;

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, bool|float|int|string|null>
     */
    public function handle(Form $form, array $values): array
    {
        $schema = $form->schema;

        if (! $schema instanceof DataCollection) {
            return [];
        }

        $initialValues = [];

        foreach ($schema as $field) {
            if (! $field instanceof FormFieldData || $field->type !== FormFieldType::Hidden) {
                continue;
            }

            if (! array_key_exists($field->key, $values)) {
                continue;
            }

            $value = $values[$field->key];

            if (! is_bool($value) && ! is_float($value) && ! is_int($value) && ! is_string($value) && $value !== null) {
                continue;
            }

            if (is_string($value) && Str::length($value) > self::MAXIMUM_STRING_LENGTH) {
                continue;
            }

            $initialValues[$field->key] = $value;
        }

        return $initialValues;
    }
}
