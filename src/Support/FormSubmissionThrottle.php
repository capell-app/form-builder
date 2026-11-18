<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support;

use Capell\Core\Support\Http\PublicEndpointThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public form submissions per address and form, and signed payment checkout links, as declared in capell.json `security.throttles`.
 *
 * Spent with the form as subject, it also caps one address at
 * PublicEndpointThrottle::CEILING_MULTIPLIER times the limit across every form. Limits are read
 * from `capell-form-builder.throttle` each time a submission is throttled.
 */
final class FormSubmissionThrottle
{
    public const string PURPOSE = 'capell-form-builder-submit';

    /** Each signed checkout link opens a payment provider session, so replaying one is bounded per address. */
    public const string CHECKOUT = 'capell-form-builder-checkout';

    public static function register(): void
    {
        RateLimiter::for(self::PURPOSE, static fn (Request $request): array => self::throttle()->limitsFor($request));
        RateLimiter::for(self::CHECKOUT, static fn (Request $request): array => self::checkout()->limitsFor($request));
    }

    public static function throttle(): PublicEndpointThrottle
    {
        return PublicEndpointThrottle::for(self::PURPOSE)
            ->allow(self::configured('max_attempts', 12))
            ->perSeconds(self::configured('decay_seconds', 60))
            ->ignoringActor();
    }

    public static function checkout(): PublicEndpointThrottle
    {
        return PublicEndpointThrottle::for(self::CHECKOUT)->allow(10)->perSeconds(60)->ignoringActor();
    }

    private static function configured(string $key, int $default): int
    {
        $value = config('capell-form-builder.throttle.' . $key, $default);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
