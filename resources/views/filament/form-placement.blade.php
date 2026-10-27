<div
    class="space-y-4"
    data-form-placement
>
    @if ($placement->handle !== null)
        <p>{{ __('capell-form-builder::placement.introduction', ['site' => $placement->siteName]) }}</p>
        <ol class="list-decimal space-y-3 pl-5">
            <li>
                {{ __('capell-form-builder::placement.developer') }}
                <code data-form-placement-handle>{{ $placement->handle }}</code>
            </li>
            <li>{{ __('capell-form-builder::placement.preview') }}</li>
            <li>{{ __('capell-form-builder::placement.test') }}</li>
        </ol>
        @unless ($placement->storesSubmissions)
            <p>{{ __('capell-form-builder::placement.storage_disabled') }}</p>
        @endunless
    @else
        <p data-form-placement-unavailable>{{ __('capell-form-builder::placement.inactive') }}</p>
    @endif
    @if ($placement->submissionsUrl !== null)
        <a
            href="{{ $placement->submissionsUrl }}"
            data-form-submissions
            class="inline-flex min-h-11 items-center underline"
            >{{ __('capell-form-builder::placement.submissions') }}</a
        >
    @endif
</div>
