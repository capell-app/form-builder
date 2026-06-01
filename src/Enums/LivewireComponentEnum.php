<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Enums;

use Capell\Core\Enums\Attribute\Component;
use Capell\Core\Enums\Attribute\EnumAttributeHelper;
use Capell\Core\Enums\Attribute\EnumAttributeInterface;
use Capell\FormBuilder\Livewire\FormComponent;
use Capell\FormBuilder\Livewire\FormElementComponent;

/** @implements EnumAttributeInterface<Component> */
enum LivewireComponentEnum: string implements EnumAttributeInterface
{
    use EnumAttributeHelper;

    #[Component(FormComponent::class)]
    case Form = 'capell-form-builder::form';

    #[Component(FormElementComponent::class)]
    case FormElement = 'capell-form-builder::element.form';

    /**
     * @return array<string, class-string|null>
     */
    public static function getComponents(): array
    {
        $attributes = self::getAllCaseAttributes(Component::class);

        /** @var array<string, class-string|null> $components */
        $components = array_map(
            static fn (?Component $attribute): ?string => $attribute?->class !== null && class_exists($attribute->class)
                ? $attribute->class
                : null,
            $attributes,
        );

        return $components;
    }
}
