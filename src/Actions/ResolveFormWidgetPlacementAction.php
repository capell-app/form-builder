<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Contracts\Capabilities\LayoutWidgetHost;
use Capell\Core\Data\Capabilities\LayoutWidgetPlacementData;
use Capell\FormBuilder\Data\FormWidgetPlacementData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Reads which form a layout widget occurrence shows from the widget's
 * `form_handle` setting. It runs while public Blade renders, so it only reads
 * the widgets the layout widget host already loaded for the current page and never
 * queries. A reference for another site, or a widget with no form chosen,
 * resolves to null and the widget renders nothing.
 *
 * @method static FormWidgetPlacementData|null run(string $widgetReference)
 */
final class ResolveFormWidgetPlacementAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly LayoutWidgetHost $host) {}

    public function handle(string $widgetReference): ?FormWidgetPlacementData
    {
        $placement = $this->host->placement($widgetReference);
        $handle = $placement?->metaString('form_handle');

        if (! $placement instanceof LayoutWidgetPlacementData || $handle === null) {
            return null;
        }

        return new FormWidgetPlacementData(
            handle: $handle,
            instanceId: 'form-widget-' . substr(hash('xxh128', $placement->containerKey . "\0" . $placement->widgetKey . "\0" . $placement->occurrence), 0, 16),
        );
    }
}
