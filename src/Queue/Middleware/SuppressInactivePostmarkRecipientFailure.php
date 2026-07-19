<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Queue\Middleware;

use Closure;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Throwable;

final class SuppressInactivePostmarkRecipientFailure
{
    /** @param Closure(object): void $next */
    public function handle(object $job, Closure $next): void
    {
        try {
            $next($job);
        } catch (Throwable $throwable) {
            if (! self::matches($throwable)) {
                throw $throwable;
            }
        }
    }

    private static function matches(Throwable $throwable): bool
    {
        if (! $throwable instanceof HttpTransportException) {
            return false;
        }

        try {
            $payload = json_decode($throwable->getResponse()->getContent(false), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return is_array($payload) && ($payload['ErrorCode'] ?? null) === 406;
    }
}
