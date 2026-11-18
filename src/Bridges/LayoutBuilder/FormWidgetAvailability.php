<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Bridges\LayoutBuilder;

use Capell\FormBuilder\Actions\FindActiveSiteFormAction;
use Capell\FormBuilder\Enums\LivewireComponentEnum;
use Capell\FormBuilder\Models\Form;
use Capell\Frontend\Data\FrontendRenderContextData;
use Capell\LayoutBuilder\Contracts\PublicLayoutWidgetAvailability;
use Capell\LayoutBuilder\Models\Widget;
use Override;
use WeakMap;

/**
 * Tells Layout Builder, before public Blade renders, that a Form Builder form
 * widget has nothing to show: no form chosen, or the page's site has no active
 * form with that key. Layout Builder then leaves the widget out entirely, so the
 * page carries no empty wrapper.
 */
final class FormWidgetAvailability implements PublicLayoutWidgetAvailability
{
    /**
     * Per page render, whether each site and handle has an active form, so a form
     * used by several widgets on one page is looked up once.
     *
     * @var WeakMap<object, array<string, bool>>
     */
    private WeakMap $lookups;

    public function __construct()
    {
        $this->lookups = new WeakMap;
    }

    #[Override]
    public function available(Widget $widget, FrontendRenderContextData $context): bool
    {
        if ($widget->getMetaComponent() !== LivewireComponentEnum::LayoutWidget->value) {
            return true;
        }

        $handle = $widget->getMeta('form_handle');
        $site = $context->site;

        if (! is_string($handle) || trim($handle) === '' || $site === null) {
            return false;
        }

        $render = $context->publicRenderData ?? $context;
        $lookup = $site->id . "\0" . trim($handle);
        $known = $this->lookups[$render] ?? [];

        if (! array_key_exists($lookup, $known)) {
            $known[$lookup] = FindActiveSiteFormAction::run($site->id, $handle) instanceof Form;
            $this->lookups[$render] = $known;
        }

        return $known[$lookup];
    }
}
