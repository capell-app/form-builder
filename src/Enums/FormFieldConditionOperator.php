<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Enums;

use Filament\Support\Contracts\HasLabel;

enum FormFieldConditionOperator: string implements HasLabel
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Filled = 'filled';
    case Blank = 'blank';
    case Contains = 'contains';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';

    public function getLabel(): string
    {
        return match ($this) {
            self::Equals => __('capell-form-builder::form.condition_operator.equals'),
            self::NotEquals => __('capell-form-builder::form.condition_operator.not_equals'),
            self::Filled => __('capell-form-builder::form.condition_operator.filled'),
            self::Blank => __('capell-form-builder::form.condition_operator.blank'),
            self::Contains => __('capell-form-builder::form.condition_operator.contains'),
            self::GreaterThan => __('capell-form-builder::form.condition_operator.greater_than'),
            self::LessThan => __('capell-form-builder::form.condition_operator.less_than'),
        };
    }
}
