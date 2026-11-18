@php
    $awaitingForm = $formReference === '' && $formHandle !== '' && ! $loadAttempted;
@endphp

<div
    class="capell-form-element"
    @if ($awaitingForm) wire:init="loadForm" @endif
    @if ($awaitingForm && $hideWhenUnavailable) wire:loading.attr="aria-busy" @endif
>
    @if ($awaitingForm && $hideWhenUnavailable)
        <noscript>
            <p>{{ __('capell-form-builder::widget.requires_javascript') }}</p>
        </noscript>
    @endif

    @if ($formReference !== '')
        @livewire('public-form-fields', ['formReference' => $formReference, 'instanceId' => $instanceId], key('public-form-' . $instanceId))
    @elseif (! $hideWhenUnavailable)
        <div
            class="capell-form-element__fallback"
            role="status"
        >
            <p>
                {{ $fallbackMessage !== '' ? $fallbackMessage : __('capell-form-builder::message.form_unavailable') }}
            </p>

            @if ($fallbackUrl !== null)
                <a href="{{ $fallbackUrl }}">
                    {{ $fallbackLabel !== '' ? $fallbackLabel : __('capell-form-builder::message.contact_instead') }}
                </a>
            @endif
        </div>
    @endif
</div>
