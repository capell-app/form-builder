# Extension and action examples

<!-- Maintained by scripts/generate-package-readmes.php -->

Use the action functions with records and Data objects supplied by your application.
They pass each argument to the package operation and return its result.

These adapters show container registration. Use the owning package registry when
a contract requires contributor discovery.

Contract adapters wrap an existing implementation. Call their registration function
from your service provider with that implementation; tagged contracts keep their declared tag.
Resolve the backend by its concrete class before registration so the replacement contract
does not resolve itself. Static contract metadata uses one backend class per adapter.

## Contract `Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver`

<!-- example: contract Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

final class FormBuilderWebhookHostResolverAdapter implements \Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver
{
    public function __construct(private readonly \Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver $backend) {}

    #[\Override]
    public function resolve(string $host): array
    {
        return $this->backend->resolve($host);
    }
}

function registerFormBuilderWebhookHostResolverAdapter(\Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver $backend): void
{
    app()->bind(FormBuilderWebhookHostResolverAdapter::class, static fn (): FormBuilderWebhookHostResolverAdapter => new FormBuilderWebhookHostResolverAdapter($backend));
    app()->bind(\Capell\FormBuilder\Contracts\FormBuilderWebhookHostResolver::class, FormBuilderWebhookHostResolverAdapter::class);
}
```

## Contract `Capell\FormBuilder\Contracts\SpamProtectionProvider`

<!-- example: contract Capell\FormBuilder\Contracts\SpamProtectionProvider -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

final class SpamProtectionProviderAdapter implements \Capell\FormBuilder\Contracts\SpamProtectionProvider
{
    public function __construct(private readonly \Capell\FormBuilder\Contracts\SpamProtectionProvider $backend) {}

    #[\Override]
    public function key(): string
    {
        return $this->backend->key();
    }

    #[\Override]
    public function verify(\Capell\FormBuilder\Models\Form $form, array $input, \Capell\FormBuilder\Data\SubmissionMetaData $meta): bool
    {
        return $this->backend->verify($form, $input, $meta);
    }
}

function registerSpamProtectionProviderAdapter(\Capell\FormBuilder\Contracts\SpamProtectionProvider $backend): void
{
    app()->bind(SpamProtectionProviderAdapter::class, static fn (): SpamProtectionProviderAdapter => new SpamProtectionProviderAdapter($backend));
    app()->bind(\Capell\FormBuilder\Contracts\SpamProtectionProvider::class, SpamProtectionProviderAdapter::class);
}
```

## Action `archiveSubmission`

<!-- example: action archiveSubmission -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runArchiveSubmission(\Capell\FormBuilder\Models\Submission $submission): \Capell\FormBuilder\Models\Submission
{
    return \Capell\FormBuilder\Actions\ArchiveSubmissionAction::run($submission);
}
```

## Action `buildFormAgentToolManifest`

<!-- example: action buildFormAgentToolManifest -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runBuildFormAgentToolManifest(\Illuminate\Support\Collection $steps, \Illuminate\Support\Collection $allFields, string $formId): ?array
{
    return \Capell\FormBuilder\Actions\BuildFormAgentToolManifestAction::run($steps, $allFields, $formId);
}
```

## Action `buildFormSteps`

<!-- example: action buildFormSteps -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runBuildFormSteps(\Capell\FormBuilder\Models\Form $form, array $input = []): \Illuminate\Support\Collection
{
    return \Capell\FormBuilder\Actions\BuildFormStepsAction::run($form, $input);
}
```

## Action `buildFormValidationRules`

<!-- example: action buildFormValidationRules -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runBuildFormValidationRules(\Capell\FormBuilder\Models\Form $form, array $input = []): array
{
    return \Capell\FormBuilder\Actions\BuildFormValidationRulesAction::run($form, $input);
}
```

## Action `buildSubmissionPayloadData`

<!-- example: action buildSubmissionPayloadData -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runBuildSubmissionPayloadData(\Capell\FormBuilder\Models\Form $form, array $validated, bool $storeUploads = true): \Capell\FormBuilder\Data\SubmissionPayloadData
{
    return \Capell\FormBuilder\Actions\BuildSubmissionPayloadDataAction::run($form, $validated, $storeUploads);
}
```

## Action `calculateFormFieldValues`

<!-- example: action calculateFormFieldValues -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runCalculateFormFieldValues(\Capell\FormBuilder\Models\Form $form, array $input = []): array
{
    return \Capell\FormBuilder\Actions\CalculateFormFieldValuesAction::run($form, $input);
}
```

## Action `calculateSubmissionSpamScore`

<!-- example: action calculateSubmissionSpamScore -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runCalculateSubmissionSpamScore(\Capell\FormBuilder\Models\Form $form, array $input, \Capell\FormBuilder\Data\SubmissionMetaData $meta): \Capell\FormBuilder\Data\SubmissionSpamScoreData
{
    return \Capell\FormBuilder\Actions\CalculateSubmissionSpamScoreAction::run($form, $input, $meta);
}
```

## Action `createFormPaymentCheckout`

<!-- example: action createFormPaymentCheckout -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runCreateFormPaymentCheckout(\Capell\FormBuilder\Models\Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null): \Capell\Payments\Models\CheckoutSession
{
    return \Capell\FormBuilder\Actions\CreateFormPaymentCheckoutSessionAction::run($submission, $successUrl, $cancelUrl);
}
```

## Action `createFormPaymentCheckoutRedirectUrl`

<!-- example: action createFormPaymentCheckoutRedirectUrl -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runCreateFormPaymentCheckoutRedirectUrl(\Capell\FormBuilder\Models\Submission $submission): ?string
{
    return \Capell\FormBuilder\Actions\CreateFormPaymentCheckoutRedirectUrlAction::run($submission);
}
```

## Action `createFormPaymentCheckoutUrl`

<!-- example: action createFormPaymentCheckoutUrl -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runCreateFormPaymentCheckoutUrl(\Capell\FormBuilder\Models\Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null, ?int $ttlMinutes = null): string
{
    return \Capell\FormBuilder\Actions\CreateFormPaymentCheckoutUrlAction::run($submission, $successUrl, $cancelUrl, $ttlMinutes);
}
```

## Action `createSubmission`

<!-- example: action createSubmission -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runCreateSubmission(\Capell\FormBuilder\Models\Form $form, array $input, \Capell\FormBuilder\Data\SubmissionMetaData $meta): \Capell\FormBuilder\Models\Submission
{
    return \Capell\FormBuilder\Actions\CreateSubmissionAction::run($form, $input, $meta);
}
```

## Action `dispatchUnstoredFormSubmission`

<!-- example: action dispatchUnstoredFormSubmission -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runDispatchUnstoredFormSubmission(\Capell\FormBuilder\Models\Form $form, array $input, \Capell\FormBuilder\Data\SubmissionMetaData $meta): \Capell\FormBuilder\Data\FormSubmissionData
{
    return \Capell\FormBuilder\Actions\DispatchUnstoredFormSubmissionAction::run($form, $input, $meta);
}
```

## Action `evaluateFormFieldVisibility`

<!-- example: action evaluateFormFieldVisibility -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runEvaluateFormFieldVisibility(\Capell\FormBuilder\Data\FormFieldData $field, array $input): bool
{
    return \Capell\FormBuilder\Actions\EvaluateFormFieldVisibilityAction::run($field, $input);
}
```

## Action `markSubmissionRead`

<!-- example: action markSubmissionRead -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runMarkSubmissionRead(\Capell\FormBuilder\Models\Submission $submission): \Capell\FormBuilder\Models\Submission
{
    return \Capell\FormBuilder\Actions\MarkSubmissionReadAction::run($submission);
}
```

## Action `markSubmissionSpam`

<!-- example: action markSubmissionSpam -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runMarkSubmissionSpam(\Capell\FormBuilder\Models\Submission $submission): \Capell\FormBuilder\Models\Submission
{
    return \Capell\FormBuilder\Actions\MarkSubmissionSpamAction::run($submission);
}
```

## Action `replyToSubmission`

<!-- example: action replyToSubmission -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runReplyToSubmission(\Capell\FormBuilder\Models\Submission $submission, string $subject, string $message): void
{
    \Capell\FormBuilder\Actions\ReplyToSubmissionAction::run($submission, $subject, $message);
}
```

## Action `resolveVisibleFormFields`

<!-- example: action resolveVisibleFormFields -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runResolveVisibleFormFields(\Capell\FormBuilder\Models\Form $form, array $input = []): \Illuminate\Support\Collection
{
    return \Capell\FormBuilder\Actions\ResolveVisibleFormFieldsAction::run($form, $input);
}
```

## Action `sendSubmissionNotification`

<!-- example: action sendSubmissionNotification -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\FormBuilder;

function runSendSubmissionNotification(\Capell\FormBuilder\Models\Submission $submission): void
{
    \Capell\FormBuilder\Actions\SendSubmissionNotificationAction::run($submission);
}
```
