<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Contracts;

/**
 * @deprecated Bind Core's \Capell\Core\Contracts\Security\ResolvesHostAddresses instead. Default webhook
 *             delivery resolves through Core's guarded client; this contract only serves the
 *             `allow_private_urls` operator opt-in path and will be removed with it.
 */
interface FormBuilderWebhookHostResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array;
}
