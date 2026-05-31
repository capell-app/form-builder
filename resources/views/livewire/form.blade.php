@php
    use Capell\FormBuilder\Enums\FormFieldType;
@endphp

<div class="capell-form-builder-form capell-form w-full">
    @if ($form)
        @if ($submitted)
            <p
                class="capell-form__message rounded-md border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-900"
                role="status"
                aria-live="polite"
                tabindex="-1"
                x-data
                x-init="$nextTick(() => $el.focus())"
            >
                {{ $settings->successMessage ?? __('capell-form-builder::message.form_submitted') }}
            </p>
        @else
            <form
                wire:submit="submit"
                wire:target="submit"
                class="capell-form__form space-y-5"
            >
                <h2
                    class="capell-form__title text-xl font-semibold text-gray-950"
                >
                    {{ $form->name }}
                </h2>

                @if ($form->description)
                    <p
                        class="capell-form__description text-sm leading-6 text-gray-600"
                    >
                        {{ $form->description }}
                    </p>
                @endif

                @if ($errors->any())
                    <div
                        class="capell-form__error-summary rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900"
                        role="alert"
                        tabindex="-1"
                        x-data
                        x-init="$nextTick(() => $el.focus())"
                    >
                        <p
                            class="capell-form__error-summary-title font-semibold"
                        >
                            {{ __('capell-form-builder::form.errors_heading') }}
                        </p>

                        <ul
                            class="capell-form__error-summary-list mt-2 list-disc space-y-1 pl-5"
                        >
                            @error('data')
                                <li>
                                    {{ $message }}
                                </li>
                            @enderror

                            @foreach ($fields as $field)
                                @php
                                    $errorKey = 'data.' . $field->key;
                                @endphp

                                @error($errorKey)
                                    <li>
                                        <a
                                            href="#capell-form-{{ $formInstanceId }}-{{ $field->key }}"
                                            class="underline underline-offset-2"
                                        >
                                            {{ $field->label }}:
                                            {{ $message }}
                                        </a>
                                    </li>
                                @enderror
                            @endforeach
                        </ul>
                    </div>
                @endif

                @foreach ($fields as $field)
                    @php
                        $fieldId = 'capell-form-' . $formInstanceId . '-' . $field->key;
                        $errorKey = 'data.' . $field->key;
                        $helpId = $fieldId . '-help';
                        $errorId = $fieldId . '-error';
                        $describedBy = collect([
                            $field->helpText ? $helpId : null,
                            $errors->has($errorKey) ? $errorId : null,
                        ])->filter()->implode(' ');
                    @endphp

                    @if ($field->type === FormFieldType::Hidden)
                        <input
                            type="hidden"
                            wire:model="data.{{ $field->key }}"
                            id="{{ $fieldId }}"
                        />
                    @elseif ($field->type === FormFieldType::Honeypot)
                        <input
                            type="text"
                            wire:model="data.{{ $field->key }}"
                            id="{{ $fieldId }}"
                            class="hidden"
                            tabindex="-1"
                            autocomplete="off"
                            aria-hidden="true"
                        />
                    @else
                        <div class="capell-form__field space-y-2">
                            <label
                                for="{{ $fieldId }}"
                                class="capell-form__label block text-sm font-medium text-gray-900"
                            >
                                {{ $field->label }}
                            </label>

                            @if ($field->type === FormFieldType::Textarea)
                                <textarea
                                    wire:model="data.{{ $field->key }}"
                                    id="{{ $fieldId }}"
                                    class="capell-form__control block min-h-28 w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900"
                                    placeholder="{{ $field->placeholder }}"
                                    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                    aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
                                    @required($field->required)
                                ></textarea>
                            @elseif ($field->type === FormFieldType::Select)
                                <select
                                    wire:model="data.{{ $field->key }}"
                                    id="{{ $fieldId }}"
                                    class="capell-form__control block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900"
                                    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                    aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
                                    @required($field->required)
                                >
                                    <option value="">
                                        {{ __('capell-form-builder::form.select_placeholder') }}
                                    </option>

                                    @foreach ($field->options as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}">
                                            {{ $optionLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($field->type === FormFieldType::Checkbox)
                                <input
                                    type="checkbox"
                                    wire:model="data.{{ $field->key }}"
                                    id="{{ $fieldId }}"
                                    class="capell-form__checkbox h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900"
                                    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                    aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
                                    @required($field->required)
                                />
                            @elseif ($field->type === FormFieldType::File)
                                <input
                                    type="file"
                                    wire:model="data.{{ $field->key }}"
                                    id="{{ $fieldId }}"
                                    class="capell-form__control block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900"
                                    @if ($field->acceptedFileTypes !== []) accept="{{ collect($field->acceptedFileTypes)->map(fn (string $type): string => str_starts_with($type, '.') ? $type : '.' . $type)->implode(',') }}" @endif
                                    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                    aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
                                    @required($field->required)
                                />
                            @elseif ($field->type === FormFieldType::Calculation)
                                <input
                                    type="number"
                                    wire:model="data.{{ $field->key }}"
                                    id="{{ $fieldId }}"
                                    class="capell-form__control block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900"
                                    readonly
                                    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                    aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
                                />
                            @else
                                <input
                                    type="{{ in_array($field->type, [FormFieldType::Number, FormFieldType::Payment], true) ? 'number' : $field->type->value }}"
                                    wire:model="data.{{ $field->key }}"
                                    id="{{ $fieldId }}"
                                    class="capell-form__control block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900"
                                    placeholder="{{ $field->placeholder }}"
                                    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                                    aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
                                    @required($field->required)
                                />
                            @endif

                            @if ($field->helpText)
                                <p
                                    id="{{ $helpId }}"
                                    class="capell-form__help text-sm text-gray-500"
                                >
                                    {{ $field->helpText }}
                                </p>
                            @endif

                            @error($errorKey)
                                <p
                                    id="{{ $errorId }}"
                                    class="capell-form__error text-sm font-medium text-red-700"
                                    role="alert"
                                >
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    @endif
                @endforeach

                <button
                    type="submit"
                    class="capell-form__submit inline-flex min-h-10 items-center justify-center rounded-md bg-gray-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 disabled:cursor-not-allowed disabled:opacity-60"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                >
                    <span wire:loading.remove wire:target="submit">
                        {{ __('capell-form-builder::form.submit') }}
                    </span>
                    <span wire:loading wire:target="submit">
                        {{ __('capell-form-builder::form.submitting') }}
                    </span>
                </button>
            </form>
        @endif
    @endif
</div>
