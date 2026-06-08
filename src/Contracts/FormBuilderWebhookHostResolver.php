<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Contracts;

interface FormBuilderWebhookHostResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array;
}
