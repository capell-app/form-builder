<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

final class RedactSubmissionWebhookErrorMessageAction
{
    use AsFake;
    use AsObject;

    public function handle(Throwable|string $error, ?string $webhookUrl = null): string
    {
        $redacted = $error instanceof Throwable ? $error->getMessage() : $error;

        foreach ($this->knownSensitiveValues($webhookUrl) as $value) {
            $redacted = str_replace($value, '[redacted]', $redacted);
        }

        foreach ($this->patterns() as $pattern => $replacement) {
            $redacted = preg_replace($pattern, $replacement, $redacted) ?? $redacted;
        }

        return $redacted;
    }

    /**
     * @return list<non-empty-string>
     */
    private function knownSensitiveValues(?string $webhookUrl): array
    {
        if ($webhookUrl === null || $webhookUrl === '') {
            return [];
        }

        $parts = parse_url($webhookUrl);

        if (! is_array($parts)) {
            return [];
        }

        $values = [];

        foreach (['user', 'pass', 'query'] as $key) {
            $value = $parts[$key] ?? null;

            if (is_string($value)) {
                $values[] = $value;
            }
        }

        if (is_string($parts['query'] ?? null)) {
            parse_str($parts['query'], $query);

            array_walk_recursive($query, static function (mixed $value) use (&$values): void {
                if (is_scalar($value)) {
                    $values[] = (string) $value;
                }
            });
        }

        return array_values(array_unique(array_filter(
            $values,
            static fn (string $value): bool => mb_strlen($value) >= 8,
        )));
    }

    /**
     * @return array<non-empty-string, non-empty-string>
     */
    private function patterns(): array
    {
        return [
            '/\b(Authorization)(\s*[:=]\s*)(Bearer|Basic)\s+[^\s,;"]+/i' => '$1$2$3 [redacted]',
            '/\b(api[_-]?key|access[_-]?token|refresh[_-]?token|client[_-]?secret|webhook[_-]?secret|signature|secret|token)(\s*["\']?\s*[:=]\s*["\']?)[^"\'\s,;}{]+/i' => '$1$2[redacted]',
        ];
    }
}
