<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Support\Url\RedirectContext;
use Capell\Core\Support\Url\RedirectTargetPolicy;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static string|null run(?string $url)
 */
final class ResolveSafeSuccessRedirectUrlAction
{
    use AsFake;
    use AsObject;

    /**
     * Returns the URL only when it is a same-site relative path or an https URL, so a stored value can never send a
     * visitor to a javascript:, data: or plain-http destination.
     */
    public function handle(?string $url): ?string
    {
        $url = $url === null ? '' : trim($url);

        return resolve(RedirectTargetPolicy::class)->validate($url, new RedirectContext(schemes: ['https']))->target;
    }
}
