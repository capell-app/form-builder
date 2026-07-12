@php
    use Capell\FormBuilder\Actions\BuildSubmissionPayloadEntriesAction;

    $entries = BuildSubmissionPayloadEntriesAction::run($submission);
@endphp

<div class="space-y-4">
    @if ($entries->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('capell-form-builder::table.payload_empty') }}
        </p>
    @else
        <dl class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($entries as $entry)
                <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt
                        class="text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        {{ $entry['label'] }}
                    </dt>
                    <dd
                        class="text-sm break-words whitespace-pre-wrap text-gray-950 sm:col-span-2 dark:text-white"
                    >
                        {{ $entry['value'] }}
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
