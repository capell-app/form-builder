<?php

declare(strict_types=1);

namespace Capell\FormBuilder\LayoutBuilder;

use Capell\FormBuilder\Actions\InvalidateFormWidgetPagesAction;
use Capell\FormBuilder\Models\Form;

/**
 * Keeps cached pages honest about form widgets: when a form appears, disappears,
 * is switched on or off, or changes handle or site, the pages placing a widget
 * for its old or new handle on its old or new site are dropped from the caches.
 */
final class InvalidateFormWidgetPagesObserver
{
    public function created(Form $form): void
    {
        $this->invalidate([$form->site_id], [$form->handle]);
    }

    public function updated(Form $form): void
    {
        if (! $form->wasChanged(['is_active', 'handle', 'site_id'])) {
            return;
        }

        $this->invalidate(
            [$form->site_id, $form->getOriginal('site_id')],
            [$form->handle, $form->getOriginal('handle')],
        );
    }

    public function deleted(Form $form): void
    {
        $this->invalidate([$form->site_id], [$form->handle]);
    }

    /**
     * @param  array<int, mixed>  $siteIds
     * @param  array<int, mixed>  $handles
     */
    private function invalidate(array $siteIds, array $handles): void
    {
        InvalidateFormWidgetPagesAction::run(
            array_map(intval(...), array_filter($siteIds, is_numeric(...))),
            array_values(array_filter($handles, is_string(...))),
        );
    }
}
