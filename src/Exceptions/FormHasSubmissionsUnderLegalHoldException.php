<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Exceptions;

use RuntimeException;

/**
 * A form is deleted only once every one of its submissions has been deleted. Submissions under legal hold, and any
 * whose uploaded files could not be deleted, are kept, so the form is kept too and the operator is told how many.
 */
final class FormHasSubmissionsUnderLegalHoldException extends RuntimeException
{
    public function __construct(string $message, public readonly int $held = 0, public readonly int $failed = 0)
    {
        parent::__construct($message);
    }

    public static function make(int $held = 1, int $failed = 0): self
    {
        return new self(__('capell-form-builder::message.form_delete_blocked_by_legal_hold', ['held' => $held, 'failed' => $failed]), $held, $failed);
    }
}
