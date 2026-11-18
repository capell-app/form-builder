<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\State\FrontendState;

if (! function_exists('bindFormBuilderFrontendSite')) {
    function bindFormBuilderFrontendSite(Site $site): void
    {
        $origin = parse_url(url('/'));
        $site->siteDomains()->update([
            'domain' => $origin['host'] ?? 'localhost',
            'scheme' => $origin['scheme'] ?? 'http',
            'path' => '/',
            'port' => $origin['port'] ?? null,
        ]);
        $state = (new FrontendState)->withSite($site);

        app()->instance(FrontendContextReader::class, $state);
        Frontend::clearResolvedInstance(FrontendContextReader::class);
    }
}
