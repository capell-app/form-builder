<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds example forms and submissions for disposable screenshot runs so the
 * forms index, form schema editor and submissions table are captured populated.
 */
final class SeedFormBuilderScreenshotFixtureAction
{
    /**
     * @return array{forms: int, submissions: int}
     */
    public static function run(): array
    {
        self::assertDisposableScreenshotEnvironment();

        $siteId = DB::table('sites')->orderBy('id')->value('id');

        throw_unless(is_numeric($siteId), RuntimeException::class, 'Form Builder screenshot fixtures require an installed site.');

        $siteId = (int) $siteId;

        $contact = Form::query()->updateOrCreate(
            ['site_id' => $siteId, 'handle' => 'project-enquiry'],
            [
                'name' => 'Project enquiry',
                'description' => 'Collects new project requests from the contact page.',
                'schema' => [
                    self::field('full_name', 'Full name', 'text', true),
                    self::field('email', 'Email address', 'email', true),
                    self::field('budget', 'Estimated budget', 'select', false, ['under-5k' => 'Under £5,000', '5k-20k' => '£5,000 to £20,000', 'over-20k' => 'Over £20,000']),
                    self::field('message', 'Tell us about your project', 'textarea', true),
                ],
                'settings' => self::settings('Thanks, we will reply within one working day.'),
                'is_active' => true,
            ],
        );

        Form::query()->updateOrCreate(
            ['site_id' => $siteId, 'handle' => 'event-registration'],
            [
                'name' => 'Event registration',
                'description' => 'Registers attendees for the spring webinar.',
                'schema' => [
                    self::field('full_name', 'Full name', 'text', true),
                    self::field('email', 'Work email', 'email', true),
                    self::field('company', 'Company', 'text', false),
                ],
                'settings' => self::settings('You are registered. Check your inbox for joining details.'),
                'is_active' => true,
            ],
        );

        Form::query()->updateOrCreate(
            ['site_id' => $siteId, 'handle' => 'product-feedback'],
            [
                'name' => 'Product feedback',
                'description' => 'Retired feedback survey kept for reporting.',
                'schema' => [
                    self::field('rating', 'How likely are you to recommend us?', 'number', true),
                    self::field('comments', 'Anything else?', 'textarea', false),
                ],
                'settings' => self::settings('Thank you for your feedback.'),
                'is_active' => false,
            ],
        );

        $submissions = [
            ['2027-02-12 09:14:00', SubmissionStatus::New, ['full_name' => 'Hannah Clarke', 'email' => 'hannah@example.test', 'budget' => '5k-20k', 'message' => 'We need to migrate our marketing site before the summer campaign.']],
            ['2027-02-12 11:42:00', SubmissionStatus::New, ['full_name' => 'Omar Haddad', 'email' => 'omar@example.test', 'budget' => 'over-20k', 'message' => 'Looking for a multi-site rollout across three regions.']],
            ['2027-02-11 16:05:00', SubmissionStatus::Read, ['full_name' => 'Grace Liu', 'email' => 'grace@example.test', 'budget' => 'under-5k', 'message' => 'Could you help refresh our charity events pages?']],
            ['2027-02-10 08:30:00', SubmissionStatus::Archived, ['full_name' => 'Tom Barker', 'email' => 'tom@example.test', 'budget' => '5k-20k', 'message' => 'Following up on the proposal we discussed last month.']],
        ];

        foreach ($submissions as [$submittedAt, $status, $values]) {
            $submittedAt = CarbonImmutable::parse($submittedAt, 'UTC');

            $exists = Submission::query()
                ->where('form_id', $contact->getKey())
                ->where('submitted_at', $submittedAt)
                ->exists();

            if ($exists) {
                continue;
            }

            Submission::query()->create([
                'form_id' => $contact->getKey(),
                'site_id' => $siteId,
                'payload' => new SubmissionPayloadData(values: $values),
                'meta' => new SubmissionMetaData(url: '/contact', userAgent: 'Mozilla/5.0'),
                'status' => $status,
                'submitted_at' => $submittedAt,
                'legal_hold' => false,
            ]);
        }

        return [
            'forms' => Form::query()->where('site_id', $siteId)->whereIn('handle', ['project-enquiry', 'event-registration', 'product-feedback'])->count(),
            'submissions' => Submission::query()->where('form_id', $contact->getKey())->count(),
        ];
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private static function field(string $key, string $label, string $type, bool $required, array $options = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $required,
            'placeholder' => null,
            'help_text' => null,
            'options' => $options,
            'validation_rules' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function settings(string $successMessage): array
    {
        return [
            'success_message' => $successMessage,
            'store_submissions' => true,
            'notification_email' => null,
            'collect_ip_address' => false,
            'collect_user_agent' => true,
        ];
    }

    private static function assertDisposableScreenshotEnvironment(): void
    {
        $configuredAppPath = getenv('CAPELL_SCREENSHOT_APP_PATH');
        $basePath = realpath(base_path());
        $appPath = is_string($configuredAppPath) ? realpath($configuredAppPath) : false;
        $environment = app()->bound('config') ? config('app.env') : getenv('APP_ENV');

        throw_unless(
            in_array($environment, ['local', 'testing'], true)
                && in_array(getenv('CAPELL_SCREENSHOT_FIXTURE'), ['1', 'true', 'record-state'], true)
                && is_string($basePath)
                && is_string($appPath)
                && $basePath === $appPath,
            RuntimeException::class,
            'Form Builder screenshot fixtures require the explicit disposable local screenshot environment.',
        );
    }
}
