<div class="capell-form-element">
    @if ($formReference !== '')
        @livewire(FormComponent::class, ['formReference' => $formReference, 'instanceId' => $instanceId], key('capell-form-' . $instanceId))
    @endif
</div>
