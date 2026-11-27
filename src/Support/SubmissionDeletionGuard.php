<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support;

use Closure;
use LogicException;

/**
 * Lets submission rows be deleted only from inside DeleteSubmissionAction, which keeps a held row and keeps any row
 * whose uploads could not all be deleted. SubmissionBuilder asks it before every delete, so a model delete, a relation
 * delete or a query delete anywhere else fails loudly instead of losing a held row or orphaning its files.
 */
final class SubmissionDeletionGuard
{
    private static int $depth = 0;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function permit(Closure $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }

    public static function assertPermitted(): void
    {
        if (self::$depth === 0) {
            throw new LogicException('Form submissions are deleted only through DeleteSubmissionAction, which keeps held submissions and their files.');
        }
    }
}
