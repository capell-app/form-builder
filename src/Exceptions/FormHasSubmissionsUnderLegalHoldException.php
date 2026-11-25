<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Exceptions;

use RuntimeException;

/**
 * Deleting a form deletes its submissions with it, so a form keeping a submission under legal hold cannot be
 * deleted until the hold is released.
 */
final class FormHasSubmissionsUnderLegalHoldException extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('capell-form-builder::message.form_delete_blocked_by_legal_hold'));
    }
}
