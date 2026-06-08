<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Tests\Fixtures;

use Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver;

final readonly class StaticFormBuilderWebhookHostResolver implements FormBuilderWebhookHostResolver
{
    /**
     * @param  array<string, list<string>>  $addresses
     */
    public function __construct(private array $addresses) {}

    /**
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        return $this->addresses[$host] ?? [];
    }
}
