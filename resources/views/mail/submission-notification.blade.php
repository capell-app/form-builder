@component('mail::message')
    @php
        use Capell\FormBuilder\Actions\BuildSubmissionPayloadEntriesAction;

        $entries = BuildSubmissionPayloadEntriesAction::run($submission);
    @endphp

    # {{ __('capell-form-builder::message.notification_heading') }}

    {{ __('capell-form-builder::message.notification_intro', ['form' => $submission->form?->name ?? __('capell-form-builder::generic.form')]) }}

    @foreach ($entries as $entry)
            **{{ $entry['label'] }}:** {{ $entry['value'] }}
    @endforeach

    {{ __('capell-form-builder::message.notification_footer') }}
@endcomponent
