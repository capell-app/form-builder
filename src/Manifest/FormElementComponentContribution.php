<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionFrontendComponent;

final class FormElementComponentContribution implements ExtensionContribution, RegistersExtensionFrontendComponent
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
