# Form Builder reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/form-builder`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Form Builder is an **Available**, **Schema-owning** Capell package in the **Capell Engagement & CRM** product group. It ships as `capell-app/form-builder` and extends these surfaces: admin, frontend.

- Status: Available
- Tier: premium
- Bundle: engagement-crm
- Composer package: `capell-app/form-builder`
- Namespace: `Capell\FormBuilder`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\FormBuilder\Providers\MigrationMetadataServiceProvider`
- `Capell\FormBuilder\Providers\FormBuilderServiceProvider`

### Config files

- `packages/form-builder/config/capell-form-builder.php`

### Migrations

- `packages/form-builder/database/migrations/2026_05_10_190849_01_create_form-builder_table.php`
- `packages/form-builder/database/migrations/2026_05_10_190849_02_create_submissions_table.php`
- `packages/form-builder/database/migrations/2026_07_12_000001_add_retention_to_submissions_table.php`

### Models

- `Form`
- `Submission`

### Filament classes

- `FormResource`
- `CreateForm`
- `EditForm`
- `ListForms`
- `ListSubmissions`
- `SubmissionResource`
- `SubmissionsTable`

### Livewire components

- `FormComponent`
- `FormElementComponent`

### Route files

- `packages/form-builder/routes/payments.php`

### Policies

- `FormPolicy`
- `SubmissionPolicy`

### Extension contracts

- `FormBuilderWebhookHostResolver`
- `SpamProtectionProvider`

### Events

- `FormSubmitted`

### Actions

- `ArchiveSubmissionAction`
- `BuildFormAgentToolManifestAction`
- `BuildFormComponentValidationRulesAction`
- `BuildFormPlacementAction`
- `BuildFormStepsAction`
- `BuildFormSubmissionPrivacyExportAction`
- `BuildFormValidationAttributesAction`
- `BuildFormValidationRulesAction`
- `BuildSubmissionPayloadDataAction`
- `BuildSubmissionPayloadEntriesAction`
- `BuildSubmissionsCsvAction`
- `CalculateFormFieldValuesAction`
- `CalculateSubmissionSpamScoreAction`
- `CreateFormPaymentCheckoutRedirectUrlAction`
- `CreateFormPaymentCheckoutSessionAction`
- `CreateFormPaymentCheckoutUrlAction`
- `CreateSubmissionAction`
- `DispatchSubmissionWebhookAction`
- `DispatchUnstoredFormSubmissionAction`
- `EraseFormSubmissionPrivacyDataAction`
- `EvaluateFormFieldVisibilityAction`
- `GuardFormSubmissionRateLimitAction`
- `InstallThemeDemoFormsAction`
- `IsFormPaymentIntegrationAvailableAction`
- `MarkSubmissionReadAction`
- `MarkSubmissionSpamAction`
- `PruneExpiredFormSubmissionsAction`
- `RedactSubmissionWebhookErrorMessageAction`
- `ReplyToSubmissionAction`
- `ResolveFormComponentFormAction`
- `ResolveFormComponentStepStateAction`
- `ResolveFormInitialValuesAction`
- `ResolveFormPaymentCheckoutDataAction`
- `ResolveFormRequestSiteAction`
- `ResolveFormSubmissionPrivacyRecordIdsAction`
- `ResolveSubmissionReplyAddressAction`
- `ResolveVisibleFormFieldsAction`
- `SeedFormBuilderScreenshotFixtureAction`
- `SendSubmissionAutoresponderAction`
- `SendSubmissionNotificationAction`
- `ValidateFormFieldKeysAction`
- `ValidateFormSchemaAuthoringAction`

### Data objects

- `FormComponentStepStateData`
- `FormFieldConditionData`
- `FormFieldData`
- `FormPaymentCheckoutData`
- `FormPlacementData`
- `FormRequestContextData`
- `FormSettingsData`
- `FormStepData`
- `FormSubmissionData`
- `FormSubmissionPrivacyRecordIdsData`
- `ResolvedFormWebhookEndpointData`
- `SubmissionMetaData`
- `SubmissionPayloadData`
- `SubmissionSpamScoreData`

### Jobs

- `DispatchSubmissionWebhookJob`

### Command signatures

- `capell:form-builder:export-submissions`
- `capell:form-builder:prune`

### Manifest action API

- `archiveSubmission: Capell\FormBuilder\Actions\ArchiveSubmissionAction`
- `buildFormAgentToolManifest: Capell\FormBuilder\Actions\BuildFormAgentToolManifestAction`
- `buildFormSteps: Capell\FormBuilder\Actions\BuildFormStepsAction`
- `buildFormValidationRules: Capell\FormBuilder\Actions\BuildFormValidationRulesAction`
- `buildSubmissionPayloadData: Capell\FormBuilder\Actions\BuildSubmissionPayloadDataAction`
- `calculateFormFieldValues: Capell\FormBuilder\Actions\CalculateFormFieldValuesAction`
- `calculateSubmissionSpamScore: Capell\FormBuilder\Actions\CalculateSubmissionSpamScoreAction`
- `createFormPaymentCheckout: Capell\FormBuilder\Actions\CreateFormPaymentCheckoutSessionAction`
- `createFormPaymentCheckoutRedirectUrl: Capell\FormBuilder\Actions\CreateFormPaymentCheckoutRedirectUrlAction`
- `createFormPaymentCheckoutUrl: Capell\FormBuilder\Actions\CreateFormPaymentCheckoutUrlAction`
- `createSubmission: Capell\FormBuilder\Actions\CreateSubmissionAction`
- `dispatchUnstoredFormSubmission: Capell\FormBuilder\Actions\DispatchUnstoredFormSubmissionAction`
- `evaluateFormFieldVisibility: Capell\FormBuilder\Actions\EvaluateFormFieldVisibilityAction`
- `markSubmissionRead: Capell\FormBuilder\Actions\MarkSubmissionReadAction`
- `markSubmissionSpam: Capell\FormBuilder\Actions\MarkSubmissionSpamAction`
- `replyToSubmission: Capell\FormBuilder\Actions\ReplyToSubmissionAction`
- `resolveVisibleFormFields: Capell\FormBuilder\Actions\ResolveVisibleFormFieldsAction`
- `sendSubmissionNotification: Capell\FormBuilder\Actions\SendSubmissionNotificationAction`

### Scheduled commands

- `capell:form-builder:prune (daily; package registered)`

### Console command classes

- `ExportSubmissionsCommand`
- `PruneExpiredFormSubmissionsCommand`
- `SeedFormBuilderScreenshotFixtureCommand`

### Manifest contributions

- `admin-resource: Capell\FormBuilder\Manifest\FormResourceContribution`
- `admin-resource: Capell\FormBuilder\Manifest\SubmissionResourceContribution`
- `console-command: Capell\FormBuilder\Manifest\FormBuilderConsoleCommandsContribution`
- `frontend-component: Capell\FormBuilder\Manifest\FormElementComponentContribution`
- `model: Capell\FormBuilder\Manifest\FormModelContribution`
- `model: Capell\FormBuilder\Manifest\SubmissionModelContribution`
- `route: Capell\FormBuilder\Manifest\FormBuilderPaymentRoutesContribution`
- `scheduled-job: Capell\FormBuilder\Manifest\FormBuilderPruneScheduleContribution`

### Health checks

- `Capell\FormBuilder\Health\FormBuilderHealthCheck`

### Blade views

- `packages/form-builder/resources/views/filament/form-placement.blade.php`
- `packages/form-builder/resources/views/filament/submissions/payload.blade.php`
- `packages/form-builder/resources/views/livewire/form-element.blade.php`
- `packages/form-builder/resources/views/livewire/form.blade.php`
- `packages/form-builder/resources/views/mail/submission-autoresponder.blade.php`
- `packages/form-builder/resources/views/mail/submission-notification.blade.php`
- `packages/form-builder/resources/views/mail/submission-reply.blade.php`

### Cache tags

- `form-builder`


## Data Model

- Required tables: `forms`, `submissions`.
- Models: `Form`, `Submission`.
- Core record references in migrations: `sites via site_id`.
- Migration files: `2026_05_10_190849_01_create_form-builder_table.php`, `2026_05_10_190849_02_create_submissions_table.php`, `2026_07_12_000001_add_retention_to_submissions_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; retention is scheduled through `capell:form-builder:prune` (daily; registered by the package provider).

<!-- prettier-ignore-end -->
