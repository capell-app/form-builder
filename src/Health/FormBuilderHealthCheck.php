<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\FormBuilder\Casts\EncryptedDataCast;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Real diagnostics for the Form Builder package.
 *
 * Backs the three declared manifest health checks:
 * - `form-builder.validation` (critical): the form and submission storage
 *   tables exist so typed-schema validation has somewhere to persist.
 * - `form-builder.spam-honeypot` (critical): spam scoring is enabled so
 *   honeypot-triggered submissions are scored rather than dispatched as
 *   genuine workflows.
 * - `form-builder.encrypted-submissions` (warning): the Submission model
 *   routes its `payload` and `meta` attributes through {@see EncryptedDataCast}
 *   so they are encrypted at rest.
 */
final class FormBuilderHealthCheck implements ChecksExtensionHealth
{
    /**
     * @var list<string>
     */
    private const array REQUIRED_TABLES = ['forms', 'submissions'];

    /**
     * @var list<string>
     */
    private const array ENCRYPTED_ATTRIBUTES = ['payload', 'meta'];

    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->storageTablesCheck(),
            $check->spamScoringEnabledCheck(),
            $check->encryptedSubmissionsCheck(),
            $check->retentionLifecycleCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    public function retentionLifecycleCheck(): DoctorCheckResultData
    {
        $days = config('capell-form-builder.retention.days');
        $configured = is_numeric($days) && (int) $days > 0
            && config('capell-form-builder.retention.schedule_enabled', true) === true;

        return new DoctorCheckResultData(
            label: 'Form Builder submission retention',
            passed: $configured,
            message: $configured
                ? sprintf('Expired submissions are scheduled for pruning after %d days, excluding legal holds.', (int) $days)
                : 'Submission retention or its scheduled prune is disabled.',
            remediation: $configured ? null : 'Configure a positive retention.days value and enable the retention schedule.',
        );
    }

    /**
     * Asserts the form and submission storage tables exist.
     */
    public function storageTablesCheck(): DoctorCheckResultData
    {
        $missingTables = $this->missingTables();

        return new DoctorCheckResultData(
            label: 'Form Builder storage tables',
            passed: $missingTables === [],
            message: $missingTables === []
                ? 'The forms and submissions tables are present.'
                : 'Missing tables: ' . implode(', ', $missingTables) . '.',
            remediation: $missingTables === []
                ? null
                : 'Run the Capell migrations to create the Form Builder storage tables.',
        );
    }

    /**
     * Asserts spam scoring is enabled so honeypot submissions are caught.
     */
    public function spamScoringEnabledCheck(): DoctorCheckResultData
    {
        $spamScoringEnabled = $this->spamScoringEnabled();

        return new DoctorCheckResultData(
            label: 'Form Builder spam scoring',
            passed: $spamScoringEnabled,
            message: $spamScoringEnabled
                ? 'Spam scoring is enabled; honeypot submissions are scored as spam.'
                : 'Spam scoring is disabled; honeypot and heuristic spam defence is inactive.',
            remediation: $spamScoringEnabled
                ? null
                : 'Set capell-form-builder.spam_scoring.enabled to true to score honeypot submissions.',
        );
    }

    /**
     * Asserts submission payload and metadata are cast through the encrypted cast.
     */
    public function encryptedSubmissionsCheck(): DoctorCheckResultData
    {
        $unencryptedAttributes = $this->unencryptedSubmissionAttributes();

        return new DoctorCheckResultData(
            label: 'Form Builder encrypted submissions',
            passed: $unencryptedAttributes === [],
            message: $unencryptedAttributes === []
                ? 'Submission payload and metadata are encrypted at rest.'
                : 'Submission attributes not encrypted at rest: ' . implode(', ', $unencryptedAttributes) . '.',
            remediation: $unencryptedAttributes === []
                ? null
                : 'Ensure the Submission model casts payload and meta through EncryptedDataCast.',
        );
    }

    /**
     * @return list<string>
     */
    public function missingTables(): array
    {
        return array_values(collect(self::REQUIRED_TABLES)
            ->reject(static fn (string $tableName): bool => Schema::hasTable($tableName))
            ->values()
            ->all());
    }

    public function spamScoringEnabled(): bool
    {
        return (bool) config('capell-form-builder.spam_scoring.enabled', true);
    }

    /**
     * @return list<string>
     */
    public function unencryptedSubmissionAttributes(): array
    {
        $casts = (new Submission)->getCasts();

        return array_values(collect(self::ENCRYPTED_ATTRIBUTES)
            ->reject(static function (string $attribute) use ($casts): bool {
                $cast = $casts[$attribute] ?? '';

                return is_string($cast) && str_starts_with($cast, EncryptedDataCast::class);
            })
            ->values()
            ->all());
    }
}
