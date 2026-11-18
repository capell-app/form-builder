<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support;

use Capell\Core\Contracts\Security\ResolvesHostAddresses;
use Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver;

/**
 * The default resolver delegates to Core, so every outbound check in the install
 * shares one DNS implementation and tests can swap the answers in one place.
 */
final readonly class DnsFormBuilderWebhookHostResolver implements FormBuilderWebhookHostResolver
{
    public function __construct(private ResolvesHostAddresses $resolver) {}

    /**
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        return array_values(array_filter(
            $this->resolver->resolve($host),
            static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP) !== false,
        ));
    }
}
