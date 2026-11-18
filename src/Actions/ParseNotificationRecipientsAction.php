<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<string> run(?string $recipients, bool $validOnly = true)
 */
final class ParseNotificationRecipientsAction
{
    use AsFake;
    use AsObject;

    /**
     * Splits a comma or semicolon separated recipient list.
     *
     * @return list<string>
     */
    public function handle(?string $recipients, bool $validOnly = true): array
    {
        $addresses = array_values(array_unique(array_filter(
            array_map(trim(...), preg_split('/[,;\n]+/', (string) $recipients) ?: []),
            static fn (string $address): bool => $address !== '',
        )));

        return $validOnly
            ? array_values(array_filter($addresses, static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false))
            : $addresses;
    }
}
