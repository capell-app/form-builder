<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Contracts\Capabilities\LayoutWidgetHost;
use Capell\Core\Data\Capabilities\LayoutWidgetPlacementRequestData;
use Capell\Core\Models\Page;
use Capell\FormBuilder\Enums\LivewireComponentEnum;
use Capell\FormBuilder\Models\Form;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Puts a form on one page as a layout widget, straight after the page's own content, through
 * the layout package that hosts widgets. Placing the same form again changes nothing. Returns
 * false when no layout package is installed or the page's layout has nowhere to put a widget.
 *
 * @method static bool run(Form $form, Page $page)
 */
final class PlaceFormOnPageAction
{
    use AsFake;
    use AsObject;

    /** The key Layout Builder gives the form widget configurator. */
    public const string WidgetConfiguratorKey = 'FormBuilderForm';

    public function __construct(private readonly LayoutWidgetHost $host) {}

    /** The key of the first widget placed for a form; later widgets add -2, -3 and so on. */
    public static function widgetKey(Form $form): string
    {
        return 'form-builder-form-' . $form->handle;
    }

    public function handle(Form $form, Page $page): bool
    {
        $component = LivewireComponentEnum::LayoutWidget->value;

        return $this->host->placeWidget($page, new LayoutWidgetPlacementRequestData(
            keyPrefix: self::widgetKey($form),
            name: (string) __('capell-form-builder::widget.widget_name', ['name' => $form->name]),
            component: $component,
            matchKey: 'form_handle',
            meta: ['component' => $component, 'form_handle' => $form->handle],
            layoutName: (string) __('capell-form-builder::widget.page_layout_name', ['page' => $page->name]),
            configurator: self::WidgetConfiguratorKey,
        ));
    }
}
