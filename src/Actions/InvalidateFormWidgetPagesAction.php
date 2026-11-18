<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Contracts\Capabilities\LayoutWidgetHost;
use Capell\Core\Data\Capabilities\LayoutWidgetMatchData;
use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\FormBuilder\Enums\LivewireComponentEnum;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Asks every public output cache to drop the pages on these sites that place a
 * form widget for one of these handles. A widget whose form is missing renders
 * nothing and so records no dependency on the form, so a cached page would keep
 * hiding the form after it is created or switched on. Returns the pages named.
 *
 * @method static list<int> run(array<int, int> $siteIds, array<int, string> $handles)
 */
final class InvalidateFormWidgetPagesAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly LayoutWidgetHost $host) {}

    /**
     * @param  array<int, int>  $siteIds
     * @param  array<int, string>  $handles
     * @return list<int>
     */
    public function handle(array $siteIds, array $handles): array
    {
        $handles = array_values(array_unique(array_filter(array_map(trim(...), $handles), static fn (string $handle): bool => $handle !== '')));

        if ($handles === []) {
            return [];
        }

        $pageIds = $this->host->pagesPlacing(
            new LayoutWidgetMatchData(LivewireComponentEnum::LayoutWidget->value, 'form_handle', $handles),
            array_values($siteIds),
        );

        if ($pageIds !== []) {
            event(new FrontendSurrogateKeysInvalidated(array_map(static fn (int $id): string => 'page-' . $id, $pageIds)));
        }

        return $pageIds;
    }
}
