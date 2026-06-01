<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\CalculateSubmissionSpamScoreAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;

it('scores honeypots excessive links blocked keywords and repeated values', function (): void {
    config()->set('capell-form-builder.spam_scoring.max_links', 1);
    config()->set('capell-form-builder.spam_scoring.blocked_keywords', ['rank-fast']);

    $form = Form::factory()->make([
        'schema' => [
            ['key' => 'company_website', 'label' => 'Company website', 'type' => 'honeypot'],
        ],
    ]);

    $score = CalculateSubmissionSpamScoreAction::run(
        form: $form,
        input: [
            'company_website' => 'https://spam.example',
            'message' => 'rank-fast https://a.example https://b.example Same content Same content Same content',
        ],
        meta: new SubmissionMetaData,
    );

    expect($score->score)->toBe(100)
        ->and($score->reasons)->toContain('honeypot')
        ->and($score->reasons)->toContain('too_many_links')
        ->and($score->reasons)->toContain('blocked_keyword:rank-fast')
        ->and($score->reasons)->toContain('missing_user_agent');
});

it('returns a clean score when scoring is disabled', function (): void {
    config()->set('capell-form-builder.spam_scoring.enabled', false);

    $score = CalculateSubmissionSpamScoreAction::run(
        form: Form::factory()->make(),
        input: ['message' => 'https://a.example https://b.example'],
        meta: new SubmissionMetaData,
    );

    expect($score->score)->toBe(0)
        ->and($score->reasons)->toBe([]);
});
