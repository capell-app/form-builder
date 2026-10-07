<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Models\Site;
use Capell\FormBuilder\Support\FormRequestContext;
use Capell\Frontend\Support\Loader\SiteLoader;
use Capell\Frontend\Support\Loader\SiteResolver;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/** @method static Site|null run() */
final class ResolveFormRequestSiteAction
{
    use AsFake;
    use AsObject;

    public function handle(): ?Site
    {
        $request = request();

        try {
            $context = FormRequestContext::current();
            $url = FormRequestContext::url();
            if ($context === null || $url === null) {
                return null;
            }

            // Request attributes distinguish a cached rejection from an absent
            // result and keep bundled paths and issuing origins independent.
            $key = self::class . ':' . hash('sha256', $url . "\0" . $context->origin);
            if ($request->attributes->has($key)) {
                $site = $request->attributes->get($key);

                return $site instanceof Site ? $site : null;
            }
            $request->attributes->set($key, null);

            [$site] = SiteResolver::resolve($url, SiteLoader::getSites());

            // The loader caches models, so recheck persisted revocation once
            // per request even when the domain resolution came from its cache.
            $site = Site::query()->enabled()->whereKey($site->getKey())->first();

            // A root wildcard can still resolve to the issuing site on another
            // site's host outside its mount. Bind the issuing origin as well.
            if ($context->origin !== $request->getSchemeAndHttpHost()) {
                return null;
            }

            $request->attributes->set($key, $site);

            return $site;
        } catch (Throwable) {
            return null;
        }
    }
}
