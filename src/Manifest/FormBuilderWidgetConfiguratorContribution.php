<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Override;

final class FormBuilderWidgetConfiguratorContribution implements ExtensionContribution
{
    #[Override]
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
