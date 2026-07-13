<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionRoute;

final class FormBuilderPaymentRoutesContribution implements ExtensionContribution, RegistersExtensionRoute
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
