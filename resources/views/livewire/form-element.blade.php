@php
    use Capell\FormBuilder\Livewire\FormComponent;
@endphp

<div class="capell-form-element capell-form-element">
    @if ($formReference !== '')
        @livewire(FormComponent::class, ['formReference' => $formReference, 'instanceId' => $instanceId], key('capell-form-' . $instanceId))
    @endif
</div>
