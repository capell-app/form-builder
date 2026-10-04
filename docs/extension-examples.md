# Form Builder extension examples

These examples use the package's declared Action API. The manifest and the
Action classes are the source of truth for the available names and signatures.

## Mark a submission as read

Use the `markSubmissionRead` Action when an admin workflow has finished
reviewing a stored submission. It updates the submission status and returns the
same `Submission` instance.

<!-- example: action markSubmissionRead -->

```php
<?php

use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Actions\MarkSubmissionReadAction;

/** @var Submission $submission */
$submission = MarkSubmissionReadAction::run($submission);
```

For an existing stored `Capell\FormBuilder\Models\Submission`, the Action
updates and returns that record; it does not create a new record as part of
this operation.

## Archive a submission

Use the `archiveSubmission` Action when an admin workflow has finished with a
stored submission. It updates the submission status and returns the same
`Submission` instance.

<!-- example: action archiveSubmission -->

```php
<?php

use Capell\FormBuilder\Actions\ArchiveSubmissionAction;
use Capell\FormBuilder\Models\Submission;

/** @var Submission $submission */
$submission = ArchiveSubmissionAction::run($submission);
```

For an existing stored `Capell\FormBuilder\Models\Submission`, the Action
sets its status to archived and returns that record; it does not create a new
record as part of this operation.

## Mark a submission as spam

Use the `markSubmissionSpam` Action when a stored submission should be
classified as spam. It updates the submission status and returns the same
`Submission` instance.

<!-- example: action markSubmissionSpam -->

```php
<?php

use Capell\FormBuilder\Actions\MarkSubmissionSpamAction;
use Capell\FormBuilder\Models\Submission;

/** @var Submission $submission */
$submission = MarkSubmissionSpamAction::run($submission);
```

For an existing stored `Capell\FormBuilder\Models\Submission`, the Action
sets its status to spam and returns that record; it does not create a new
record as part of this operation.

## Reply to a submission

Use the `replyToSubmission` Action to send an email reply for a stored
submission. It resolves the submission's reply address, sends the supplied
subject and message, and returns no value.

<!-- example: action replyToSubmission -->

```php
<?php

use Capell\FormBuilder\Actions\ReplyToSubmissionAction;
use Capell\FormBuilder\Models\Submission;

/** @var Submission $submission */
ReplyToSubmissionAction::run(
    submission: $submission,
    subject: 'Thanks for getting in touch',
    message: 'We have received your message and will reply soon.',
);
```

If the submission has no resolvable reply address, the Action throws a
validation exception instead of sending the email. A new submission is marked
as read after the reply is sent; other statuses are unchanged.

## Send a submission notification

Use the `sendSubmissionNotification` Action to queue an email notification for
a stored, non-spam submission when its form has a notification email address.
The Action returns no value.

<!-- example: action sendSubmissionNotification -->

```php
<?php

use Capell\FormBuilder\Actions\SendSubmissionNotificationAction;
use Capell\FormBuilder\Models\Submission;

/** @var Submission $submission */
SendSubmissionNotificationAction::run($submission);
```

Spam submissions and forms without a configured notification address are
ignored. If queuing the notification fails, the Action logs a warning instead
of allowing the exception to escape.

## Create a submission

The `createSubmission` manifest action maps to
`Capell\FormBuilder\Actions\CreateSubmissionAction`. Call its public `run`
contract with a `Form`, an `array<string, mixed>` input, and
`SubmissionMetaData` to validate and persist a new submission.

<!-- example: action createSubmission -->

```php
<?php

use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;

/** @var Form $form */
$submission = CreateSubmissionAction::run(
    form: $form,
    input: ['email' => 'ben@example.com'],
    meta: new SubmissionMetaData(
        ipAddress: '127.0.0.1',
        userAgent: 'Example client',
        url: 'https://example.test/contact',
    ),
);
```

The returned `Submission` is persisted with the form and site association,
validated storable payload, supplied request metadata, and a submission
timestamp. The Action recalculates `spamScore` and `spamReasons` before storing
the metadata, so those values on the returned submission reflect its scoring.

## Evaluate form field visibility

The `evaluateFormFieldVisibility` manifest key maps to
`Capell\FormBuilder\Actions\EvaluateFormFieldVisibilityAction`. Call its public
`run` contract with a `FormFieldData` object and the current input array to get
a `bool` visibility result.

<!-- example: action evaluateFormFieldVisibility -->

```php
<?php

use Capell\FormBuilder\Actions\EvaluateFormFieldVisibilityAction;
use Capell\FormBuilder\Data\FormFieldData;

$field = FormFieldData::from([
    'key' => 'support_details',
    'label' => 'Support details',
    'type' => 'textarea',
    'visibility_conditions' => [
        ['field_key' => 'interest', 'operator' => 'equals', 'value' => 'support'],
        ['field_key' => 'email', 'operator' => 'filled'],
    ],
]);

$visible = EvaluateFormFieldVisibilityAction::run($field, [
    'interest' => 'support',
    'email' => 'ben@example.com',
]);
```

The exact public signature is
`run(FormFieldData $field, array<string, mixed> $input): bool`. A field with no
visibility conditions returns `true`; otherwise every condition must match.
Supported operators are `equals`, `not_equals`, `filled`, `blank`, `contains`,
`greater_than`, and `less_than`. A missing input key is evaluated as `null`.

## Build form validation rules

The `buildFormValidationRules` manifest key maps to
`Capell\FormBuilder\Actions\BuildFormValidationRulesAction`. Call its public
`run` contract with the form and, when needed, the current input values.

<!-- example: action buildFormValidationRules -->

```php
<?php

use Capell\FormBuilder\Actions\BuildFormValidationRulesAction;
use Capell\FormBuilder\Models\Form;

/** @var Form $form */
$rules = BuildFormValidationRulesAction::run(
    form: $form,
);
```

The exact public signature is
`run(Form $form, array<string, mixed> $input = []): array<string, array<int, string>>`.
The returned array is keyed by field key and contains the validation rule
strings for fields visible with the supplied input. Pass input values when
conditional visibility depends on them; otherwise the default empty array is
used. The Action combines the field's required state, type-specific rules, and
permitted editor validation rules; unsupported editor rules are ignored. It
only builds the rules array and does not persist a submission.

## Build submission payload data

The `buildSubmissionPayloadData` manifest key maps to
`Capell\FormBuilder\Actions\BuildSubmissionPayloadDataAction`. Call its
public `run` contract with the form and validated values to build a
`Capell\FormBuilder\Data\SubmissionPayloadData` instance.

The exact public signature is
`::run(Form $form, array<string, mixed> $validated, bool $storeUploads = true): SubmissionPayloadData`.
The returned `SubmissionPayloadData::$values` contains values for visible form
fields that are stored in the payload and are present in `$validated`.
Non-upload values are kept as supplied. With the default `storeUploads: true`,
an `UploadedFile` is stored using the configured upload disk and directory and
its payload value contains `original_name`, `mime_type`, `size`, `disk`, and
`path`. With `storeUploads: false`, the file is not stored and its value
contains only `original_name`, `mime_type`, and `size`. A failed file store
throws a `RuntimeException`.

<!-- example: action buildSubmissionPayloadData -->

```php
<?php

use Capell\FormBuilder\Actions\BuildSubmissionPayloadDataAction;
use Capell\FormBuilder\Models\Form;

/** @var Form $form */
/** @var array<string, mixed> $validated */
$payload = BuildSubmissionPayloadDataAction::run(
    form: $form,
    validated: $validated,
);
```

## Build a form agent tool manifest

Use the `buildFormAgentToolManifest` Action to describe a supported,
single-step form as a public write tool. It accepts hydrated step and field
collections plus a form ID, and returns the manifest array or `null` when the
form is multi-step, has an invalid ID, uses spam protection, contains an
unsupported field, or has no submittable fields.

<!-- example: action buildFormAgentToolManifest -->

```php
<?php

use Capell\FormBuilder\Actions\BuildFormAgentToolManifestAction;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormStepData;
use Capell\FormBuilder\Enums\FormFieldType;
use Illuminate\Support\Collection;

$fields = collect([
    new FormFieldData(
        key: 'email',
        label: 'Email',
        type: FormFieldType::Email,
        required: true,
    ),
    new FormFieldData(
        key: 'message',
        label: 'Message',
        type: FormFieldType::Textarea,
    ),
]);

$steps = collect([
    new FormStepData(
        key: 'default',
        label: 'Contact',
        fields: $fields,
    ),
]);

/** @var Collection<int, FormStepData> $steps */
/** @var Collection<int, FormFieldData> $fields */
$manifest = BuildFormAgentToolManifestAction::run(
    steps: $steps,
    allFields: $fields,
    formId: 'contact',
);
```

The returned manifest contains the `form.submit.contact` write tool, input
properties for the supported fields, and an output schema for `submitted` or
`pending` status. Hidden, honeypot, and calculation fields are omitted from
the input schema because they are server-managed; file and payment fields are
not supported by this Action.

The exact public signature is
`::run(Collection $steps, Collection $allFields, string $formId): ?array`.
The Action returns the manifest array for one valid, single-step form with a
valid ID, spam protection disabled, supported fields, and at least one
submittable field. It returns `null` for a multi-step form, an invalid form ID,
enabled spam protection, an invalid or unsupported field, or a form with no
submittable fields.

## Create a form payment checkout URL

The `createFormPaymentCheckoutUrl` manifest key maps to
`Capell\FormBuilder\Actions\CreateFormPaymentCheckoutUrlAction`. Its public
static `run()` entrypoint accepts a `Submission`, optional success and cancel
URLs, and an optional TTL in minutes, then returns a `string`.

<!-- example: action createFormPaymentCheckoutUrl -->

```php
<?php

use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutUrlAction;
use Capell\FormBuilder\Models\Submission;

/** @var Submission $submission */
$checkoutUrl = CreateFormPaymentCheckoutUrlAction::run(
    submission: $submission,
    successUrl: 'https://example.test/success',
    cancelUrl: 'https://example.test/cancel',
    ttlMinutes: 15,
);
```

The exact contract is:

```text
run(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null, ?int $ttlMinutes = null): string
handle(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null, ?int $ttlMinutes = null): string
```

## Create a form payment checkout session

The `createFormPaymentCheckout` manifest key maps to
`Capell\FormBuilder\Actions\CreateFormPaymentCheckoutSessionAction`. Its
static `run()` entrypoint and instance `handle()` method accept a `Submission`,
optional success and cancel URLs, and return a
`Capell\Payments\Models\CheckoutSession`.

<!-- example: action createFormPaymentCheckout -->

```php
<?php

use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutSessionAction;
use Capell\FormBuilder\Models\Submission;

/** @var Submission $submission */
$checkoutSession = CreateFormPaymentCheckoutSessionAction::run(
    submission: $submission,
    successUrl: 'https://example.test/success',
    cancelUrl: 'https://example.test/cancel',
);
```

The exact contracts are:

```text
run(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null): CheckoutSession
handle(Submission $submission, ?string $successUrl = null, ?string $cancelUrl = null): CheckoutSession
```

## Calculate form field values

The `calculateFormFieldValues` manifest key maps to
`Capell\FormBuilder\Actions\CalculateFormFieldValuesAction`. Call its public
`run` contract with the form and current input values.

<!-- example: action calculateFormFieldValues -->

```php
<?php

use Capell\FormBuilder\Actions\CalculateFormFieldValuesAction;
use Capell\FormBuilder\Models\Form;

/** @var Form $form */
/** @var array<string, mixed> $input */
$values = CalculateFormFieldValuesAction::run(
    form: $form,
    input: $input,
);
```

The exact public signature is
`run(Form $form, array<string, mixed> $input = []): array<string, mixed>`.
The returned array contains the supplied input and calculated values for
visible calculation fields defined by the form. The Action does not persist
the form or input.

## Calculate a submission spam score

The `calculateSubmissionSpamScore` manifest key maps to
`Capell\FormBuilder\Actions\CalculateSubmissionSpamScoreAction`. Call its
public `run` contract with the form, submitted input, and request metadata.

<!-- example: action calculateSubmissionSpamScore -->

```php
<?php

use Capell\FormBuilder\Actions\CalculateSubmissionSpamScoreAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;

/** @var Form $form */
/** @var array<string, mixed> $input */
$spamScore = CalculateSubmissionSpamScoreAction::run(
    form: $form,
    input: $input,
    meta: new SubmissionMetaData(userAgent: 'Example client'),
);
```

The exact public signature is
`run(Form $form, array<string, mixed> $input, SubmissionMetaData $meta): SubmissionSpamScoreData`.
The returned `SubmissionSpamScoreData` contains the calculated integer `score`
and a list of string `reasons`; call `isSpam(int $threshold)` when a boolean
decision is needed. The Action returns this data without persisting the form
or submission.

## Dispatch an unstored form submission

Use the `dispatchUnstoredFormSubmission` Action when a form submission should be
validated and dispatched without creating a stored `Submission` record. It
calculates field values and spam scoring, returns a `FormSubmissionData` value,
and dispatches `FormSubmitted` for submissions that are not spam. Spam
submissions are returned with a spam status and are not dispatched.

The manifest key maps to
`Capell\FormBuilder\Actions\DispatchUnstoredFormSubmissionAction` and exposes
the following public contract:

```text
run(Form $form, array<string, mixed> $input, SubmissionMetaData $meta): FormSubmissionData
```

<!-- example: action dispatchUnstoredFormSubmission -->

```php
<?php

use Capell\FormBuilder\Actions\DispatchUnstoredFormSubmissionAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Data\FormSubmissionData;

/** @var Form $form */
$input = [
    'name' => 'Ada Lovelace',
    'email' => 'ada@example.test',
    'message' => 'Please send the product guide.',
];

$submission = DispatchUnstoredFormSubmissionAction::run(
    $form,
    $input,
    new SubmissionMetaData(
        ipAddress: request()->ip(),
        userAgent: request()->userAgent(),
        url: request()->fullUrl(),
        referer: request()->headers->get('referer'),
    ),
);

/** @var FormSubmissionData $submission */
```

The action does not persist the submission or uploaded files. Validation errors
are raised for invalid input; spam scoring and a configured honeypot can instead
produce an unstored spam result.

## Build form steps

Use the `buildFormSteps` Action to resolve the form's visible fields into
ordered `FormStepData` groups. The optional input array is used when resolving
conditional field visibility.

<!-- example: action buildFormSteps -->

```php
<?php

use Capell\FormBuilder\Actions\BuildFormStepsAction;
use Capell\FormBuilder\Models\Form;

/** @var Form $form */
$input = [];
$steps = BuildFormStepsAction::run($form, $input);
```

The Action returns an `Illuminate\Support\Collection<int, FormStepData>` and
does not persist changes to the form.

## Resolve visible form fields

Use the `resolveVisibleFormFields` Action when an extension needs the form
fields that should be rendered or processed for the current input.

<!-- example: action resolveVisibleFormFields -->

```php
<?php

use Capell\FormBuilder\Actions\ResolveVisibleFormFieldsAction;
use Capell\FormBuilder\Models\Form;

/** @var Form $form */
$input = [
    'contact_preference' => 'email',
];

$visibleFields = ResolveVisibleFormFieldsAction::run($form, $input);
```

The public `::run(Form $form, array<string, mixed> $input = []):
Collection<int, FormFieldData>` contract returns the form's fields in their
declared schema order. A `null` or other non-array schema returns an empty
`Collection`; within an array schema, non-array raw entries are skipped, while
malformed array entries may throw during `FormFieldData::from()`. Malformed
persisted JSON may instead throw during model casting, before this Action can
filter the schema. Fields whose visibility conditions do not match the input
are removed; every condition on a field must match, and a field with no
conditions is visible. The returned collection is re-indexed and contains
`FormFieldData` instances. This is a read-only resolution step: it does not
change or persist the form.

## Create a form payment checkout redirect URL

Use the `createFormPaymentCheckoutRedirectUrl` Action to resolve a checkout
redirect URL for a submission.

<!-- example: action createFormPaymentCheckoutRedirectUrl -->

```php
<?php

use Capell\FormBuilder\Actions\CreateFormPaymentCheckoutRedirectUrlAction;
use Capell\FormBuilder\Models\Submission;

/** @var Submission $submission */
$checkoutUrl = CreateFormPaymentCheckoutRedirectUrlAction::run($submission);
```

The public `::run(Submission $submission): ?string` call returns `null` when
payment integration is unavailable, the submission is not associated with a
Form Builder form containing a Payment field, or the checkout route is not
registered. When those checks pass, it returns the non-empty checkout URL
produced by `CreateFormPaymentCheckoutUrlAction`; an empty result is
normalised to `null`.
