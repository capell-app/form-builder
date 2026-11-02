<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Support\EditorValidationRuleGrammar;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Rejects authored field configuration that the public form would silently
 * ignore: validation rules outside the supported grammar and visibility
 * conditions that reference missing, self or cyclic field keys.
 *
 * @method static void run(array<array-key, array<string, mixed>> $schema, string $statePath = 'data.schema')
 */
final class ValidateFormSchemaAuthoringAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<array-key, array<string, mixed>>  $schema
     */
    public function handle(array $schema, string $statePath = 'data.schema'): void
    {
        $errors = [
            ...$this->validationRuleErrors($schema, $statePath),
            ...$this->conditionErrors($schema, $statePath),
        ];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $schema
     * @return array<string, string>
     */
    private function validationRuleErrors(array $schema, string $statePath): array
    {
        $errors = [];

        foreach ($schema as $row => $field) {
            $type = $this->fieldType($field['type'] ?? null);
            $rules = $field['validation_rules'] ?? [];

            if (! $type instanceof FormFieldType || ! is_array($rules)) {
                continue;
            }

            $problems = [];

            foreach ($rules as $rule) {
                $problem = is_string($rule)
                    ? EditorValidationRuleGrammar::problem(trim($rule), $type)
                    : null;

                if ($problem !== null) {
                    $problems[] = $problem;
                }
            }

            if ($problems !== []) {
                $errors[$statePath . '.' . $row . '.validation_rules'] = implode(' ', $problems);
            }
        }

        return $errors;
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $schema
     * @return array<string, string>
     */
    private function conditionErrors(array $schema, string $statePath): array
    {
        $keys = [];
        $dependencies = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;

            if (is_string($key) && $key !== '') {
                $keys[$key] = true;
                $dependencies[$key] = [];
            }
        }

        $errors = [];

        foreach ($schema as $row => $field) {
            $key = is_string($field['key'] ?? null) ? $field['key'] : '';
            $conditions = $field['visibility_conditions'] ?? [];

            if (! is_array($conditions)) {
                continue;
            }

            foreach ($conditions as $conditionRow => $condition) {
                $source = is_array($condition) ? ($condition['field_key'] ?? null) : null;

                if (! is_string($source) || $source === '') {
                    continue;
                }

                $path = $statePath . '.' . $row . '.visibility_conditions.' . $conditionRow . '.field_key';

                if ($source === $key) {
                    $errors[$path] = (string) __('capell-form-builder::form.admin.condition_self_reference', ['key' => $source]);

                    continue;
                }

                if (! isset($keys[$source])) {
                    $errors[$path] = (string) __('capell-form-builder::form.admin.condition_unknown_field', [
                        'key' => $source,
                        'available' => implode(', ', array_keys(array_diff_key($keys, [$key => true]))),
                    ]);

                    continue;
                }

                if ($key !== '') {
                    $dependencies[$key][$source] = $path;
                }
            }
        }

        foreach ($this->cyclicEdges($dependencies) as $path => $cycle) {
            $errors[$path] ??= (string) __('capell-form-builder::form.admin.condition_cycle', ['fields' => $cycle]);
        }

        return $errors;
    }

    /**
     * @param  array<string, array<string, string>>  $dependencies  field key => [source key => error path]
     * @return array<string, string> error path => readable cycle
     */
    private function cyclicEdges(array $dependencies): array
    {
        $cyclic = [];

        foreach ($dependencies as $start => $sources) {
            foreach ($sources as $source => $path) {
                $route = $this->pathBetween($dependencies, $source, $start);

                if ($route !== null) {
                    $cyclic[$path] = implode(' → ', [$start, ...$route]);
                }
            }
        }

        return $cyclic;
    }

    /**
     * @param  array<string, array<string, string>>  $dependencies
     * @return array<int, string>|null
     */
    private function pathBetween(array $dependencies, string $from, string $to): ?array
    {
        $queue = [[$from]];
        $seen = [$from => true];

        while ($queue !== []) {
            $route = array_shift($queue);
            $current = $route[array_key_last($route)];

            if ($current === $to) {
                return $route;
            }

            foreach (array_keys($dependencies[$current] ?? []) as $next) {
                if (! isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = [...$route, $next];
                }
            }
        }

        return null;
    }

    private function fieldType(mixed $type): ?FormFieldType
    {
        if ($type instanceof FormFieldType) {
            return $type;
        }

        return is_string($type) ? FormFieldType::tryFrom($type) : null;
    }
}
