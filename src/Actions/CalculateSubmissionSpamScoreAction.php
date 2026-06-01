<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionSpamScoreData;
use Capell\FormBuilder\Models\Form;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

final class CalculateSubmissionSpamScoreAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Form $form, array $input, SubmissionMetaData $meta): SubmissionSpamScoreData
    {
        if (! (bool) config('capell-form-builder.spam_scoring.enabled', true)) {
            return new SubmissionSpamScoreData;
        }

        $score = 0;
        $reasons = [];

        if ($this->hasTriggeredHoneypot($form, $input)) {
            $score += 100;
            $reasons[] = 'honeypot';
        }

        $submittedText = $this->submittedText($input);
        $linkCount = $this->linkCount($submittedText);
        $maxLinks = $this->maxLinks();

        if ($linkCount > $maxLinks) {
            $score += min(60, 35 + (($linkCount - $maxLinks) * 5));
            $reasons[] = 'too_many_links';
        }

        foreach ($this->blockedKeywords() as $keyword) {
            if (str_contains(Str::lower($submittedText), $keyword)) {
                $score += 30;
                $reasons[] = 'blocked_keyword:' . $keyword;
            }
        }

        if ($this->hasRepeatedValues($input)) {
            $score += 20;
            $reasons[] = 'repeated_values';
        }

        if ($meta->userAgent === null || trim($meta->userAgent) === '') {
            $score += 10;
            $reasons[] = 'missing_user_agent';
        }

        return new SubmissionSpamScoreData(
            score: min(100, $score),
            reasons: array_values(array_unique($reasons)),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasTriggeredHoneypot(Form $form, array $input): bool
    {
        foreach ($form->schema ?? [] as $field) {
            if (is_array($field)) {
                $field = FormFieldData::from($field);
            }

            if (! $field instanceof FormFieldData || ! $field->type->isSpamTrap()) {
                continue;
            }

            if (filled($input[$field->key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function submittedText(array $input): string
    {
        return collect($input)
            ->flatMap(fn (mixed $value): array => $this->textValues($value))
            ->implode(' ');
    }

    /**
     * @return list<string>
     */
    private function textValues(mixed $value): array
    {
        if (is_string($value) || is_numeric($value)) {
            return [(string) $value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatMap(fn (mixed $nestedValue): array => $this->textValues($nestedValue))
            ->values()
            ->all();
    }

    private function linkCount(string $submittedText): int
    {
        preg_match_all('/https?:\/\/|www\./i', $submittedText, $matches);

        return count($matches[0] ?? []);
    }

    private function maxLinks(): int
    {
        $maxLinks = config('capell-form-builder.spam_scoring.max_links', 5);

        return is_numeric($maxLinks) ? max(0, (int) $maxLinks) : 5;
    }

    /**
     * @return list<string>
     */
    private function blockedKeywords(): array
    {
        $keywords = config('capell-form-builder.spam_scoring.blocked_keywords', []);

        if (! is_array($keywords)) {
            return [];
        }

        return collect($keywords)
            ->filter(static fn (mixed $keyword): bool => is_string($keyword) || is_numeric($keyword))
            ->map(static fn (string|int|float $keyword): string => Str::lower(trim((string) $keyword)))
            ->filter(static fn (string $keyword): bool => $keyword !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasRepeatedValues(array $input): bool
    {
        $values = collect($input)
            ->flatMap(fn (mixed $value): array => $this->textValues($value))
            ->map(static fn (string $value): string => Str::lower(trim($value)))
            ->filter(static fn (string $value): bool => $value !== '' && mb_strlen($value) > 8)
            ->countBy();

        return $values->contains(static fn (int $count): bool => $count >= 3);
    }
}
