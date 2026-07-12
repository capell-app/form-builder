<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class FormModelContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
