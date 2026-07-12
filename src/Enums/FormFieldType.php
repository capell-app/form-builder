<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Enums;

use Filament\Support\Contracts\HasLabel;

enum FormFieldType: string implements HasLabel
{
    case Text = 'text';
    case Email = 'email';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case File = 'file';
    case Payment = 'payment';
    case Calculation = 'calculation';
    case Hidden = 'hidden';
    case Honeypot = 'honeypot';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => __('capell-form-builder::form.field_type.text'),
            self::Email => __('capell-form-builder::form.field_type.email'),
            self::Textarea => __('capell-form-builder::form.field_type.textarea'),
            self::Number => __('capell-form-builder::form.field_type.number'),
            self::Select => __('capell-form-builder::form.field_type.select'),
            self::Checkbox => __('capell-form-builder::form.field_type.checkbox'),
            self::File => __('capell-form-builder::form.field_type.file'),
            self::Payment => __('capell-form-builder::form.field_type.payment'),
            self::Calculation => __('capell-form-builder::form.field_type.calculation'),
            self::Hidden => __('capell-form-builder::form.field_type.hidden'),
            self::Honeypot => __('capell-form-builder::form.field_type.honeypot'),
        };
    }

    public function isStoredInPayload(): bool
    {
        return $this !== self::Honeypot;
    }

    public function isSpamTrap(): bool
    {
        return $this === self::Honeypot;
    }

    public function isComputed(): bool
    {
        return $this === self::Calculation;
    }
}
