<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Form;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Finds the active form a site shows for a handle, or failing that an id. Public embeds and the
 * Layout Builder widget both use it, so a form that is missing, inactive or
 * owned by another site is never shown.
 *
 * @method static Form|null run(int $siteId, int|string|null $handle)
 */
final class FindActiveSiteFormAction
{
    use AsFake;
    use AsObject;

    public function handle(int $siteId, int|string|null $handle): ?Form
    {
        if ($handle === null || trim((string) $handle) === '') {
            return null;
        }

        $handle = trim((string) $handle);
        $query = static fn (): Builder => Form::query()->active()->where('site_id', $siteId)->orderBy('id');

        // A handle that looks like a number is still a handle first: a same-site id
        // match is only the fallback for embeds that name a form by its id.
        $form = $query()->where('handle', $handle)->first();

        if (! $form instanceof Form && ctype_digit($handle)) {
            $form = $query()->whereKey((int) $handle)->first();
        }

        return $form;
    }
}
