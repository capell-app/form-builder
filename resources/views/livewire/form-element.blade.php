<div class="capell-form-element capell-form-element">
    @if ($formReference !== '')
        @livewire ('public-form-fields', ['formReference' => $formReference, 'instanceId' => $instanceId], key('public-form-' . $instanceId))
    @else
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
