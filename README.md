# Form Builder

<!-- prettier-ignore-start -->

## What This Plugin Adds

Form Builder is an **Available**, **Schema-owning** Capell package in the **Capell Engagement & CRM** product group. It ships as `capell-app/form-builder` and extends these surfaces: admin, frontend.

Form Builder adds site-scoped form schemas, conditional and multi-step fields, calculations, spam checks, optional payment handoff, and encrypted submission records.

Editors build forms and triage submissions in the admin, while visitors complete the published form through a Livewire frontend component.

Evidence: [`capell.json`](capell.json), [`src/Models/Form.php`](src/Models/Form.php), [`src/Models/Submission.php`](src/Models/Submission.php), [`src/Providers/FormBuilderServiceProvider.php`](src/Providers/FormBuilderServiceProvider.php), [`docs/overview.admin.md`](docs/overview.admin.md), [`docs/screenshots.json`](docs/screenshots.json), [`src/Filament/Resources/Forms/FormResource.php`](src/Filament/Resources/Forms/FormResource.php), [`tests/Feature/FormComponentTest.php`](tests/Feature/FormComponentTest.php).

Status details:

- Status: Available
- Tier: premium
- Bundle: engagement-crm
- Composer package: `capell-app/form-builder`
- Namespace: `Capell\FormBuilder`
- Theme key: not applicable

## Why It Matters

**For developers:** Typed field data and Actions handle visibility, validation, calculations, spam scoring, rate limits, and persistence, with a contract for replacing the spam provider.

**For teams:** Teams can publish structured forms, receive notifications, review or reply to submissions, and apply retention or legal-hold rules from one workflow.

Evidence: [`src/Data/FormFieldData.php`](src/Data/FormFieldData.php), [`src/Actions/BuildFormValidationRulesAction.php`](src/Actions/BuildFormValidationRulesAction.php), [`src/Actions/CalculateSubmissionSpamScoreAction.php`](src/Actions/CalculateSubmissionSpamScoreAction.php), [`src/Contracts/SpamProtectionProvider.php`](src/Contracts/SpamProtectionProvider.php), [`docs/admin-guide.md`](docs/admin-guide.md), [`src/Actions/CreateSubmissionAction.php`](src/Actions/CreateSubmissionAction.php), [`src/Actions/ReplyToSubmissionAction.php`](src/Actions/ReplyToSubmissionAction.php), [`tests/Integration/Actions/FormSubmissionRetentionTest.php`](tests/Integration/Actions/FormSubmissionRetentionTest.php).

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

![FormBuilder admin index](docs/screenshots/form-builder-admin-index.png)

![Create/edit form schema screen](docs/screenshots/create-edit-form-schema-screen.png)

- FormBuilder admin index (admin, required).
- Create/edit form schema screen (admin, required).
- Submissions index (admin, required).
- Frontend form output (frontend, required).
- Submission detail view (admin, optional).

## Technical Shape

- Service providers: `Capell\FormBuilder\Providers\FormBuilderServiceProvider`.
- Config files: `packages/form-builder/config/capell-form-builder.php`.
- Migrations: `packages/form-builder/database/migrations/2026_05_10_190849_01_create_form-builder_table.php`, `packages/form-builder/database/migrations/2026_05_10_190849_02_create_submissions_table.php`, `packages/form-builder/database/migrations/2026_07_12_000001_add_retention_to_submissions_table.php`.
- Models: `Form`, `Submission`.
- Filament classes: `FormResource`, `CreateForm`, `EditForm`, `ListForms`, `ListSubmissions`, `SubmissionResource`, `SubmissionsTable`.
- Livewire components: `FormComponent`, `FormElementComponent`.
- Route files: `packages/form-builder/routes/payments.php`.
- Policies: `FormPolicy`, `SubmissionPolicy`.
- Extension contracts: `FormBuilderWebhookHostResolver`, `SpamProtectionProvider`.
- Events: `FormSubmitted`.
- Actions: `ArchiveSubmissionAction`, `BuildFormComponentValidationRulesAction`, `BuildFormStepsAction`, `BuildFormSubmissionPrivacyExportAction`, `BuildFormValidationRulesAction`, `BuildSubmissionPayloadDataAction`, `BuildSubmissionPayloadEntriesAction`, `BuildSubmissionsCsvAction`, `CalculateFormFieldValuesAction`, `CalculateSubmissionSpamScoreAction`, `CreateFormPaymentCheckoutRedirectUrlAction`, `CreateFormPaymentCheckoutSessionAction`, `and 22 more`.
- Data objects: `FormComponentStepStateData`, `FormFieldConditionData`, `FormFieldData`, `FormPaymentCheckoutData`, `FormSettingsData`, `FormStepData`, `FormSubmissionData`, `FormSubmissionPrivacyRecordIdsData`, `ResolvedFormWebhookEndpointData`, `SubmissionMetaData`, `SubmissionPayloadData`, `SubmissionSpamScoreData`.
- Command signatures: `capell:form-builder:prune`.
- Manifest action API: `archiveSubmission: Capell\FormBuilder\Actions\ArchiveSubmissionAction`, `buildFormSteps: Capell\FormBuilder\Actions\BuildFormStepsAction`, `buildFormValidationRules: Capell\FormBuilder\Actions\BuildFormValidationRulesAction`, `buildSubmissionPayloadData: Capell\FormBuilder\Actions\BuildSubmissionPayloadDataAction`, `calculateFormFieldValues: Capell\FormBuilder\Actions\CalculateFormFieldValuesAction`, `calculateSubmissionSpamScore: Capell\FormBuilder\Actions\CalculateSubmissionSpamScoreAction`, `createFormPaymentCheckout: Capell\FormBuilder\Actions\CreateFormPaymentCheckoutSessionAction`, `createFormPaymentCheckoutRedirectUrl: Capell\FormBuilder\Actions\CreateFormPaymentCheckoutRedirectUrlAction`, `createFormPaymentCheckoutUrl: Capell\FormBuilder\Actions\CreateFormPaymentCheckoutUrlAction`, `createSubmission: Capell\FormBuilder\Actions\CreateSubmissionAction`, `dispatchUnstoredFormSubmission: Capell\FormBuilder\Actions\DispatchUnstoredFormSubmissionAction`, `evaluateFormFieldVisibility: Capell\FormBuilder\Actions\EvaluateFormFieldVisibilityAction`, `and 5 more`.
- Scheduled commands: `capell:form-builder:prune (daily)`.
- Console command classes: `ExportSubmissionsCommand`, `PruneExpiredFormSubmissionsCommand`.
- Manifest contributions: `admin-resource: Capell\FormBuilder\Manifest\FormResourceContribution`, `admin-resource: Capell\FormBuilder\Manifest\SubmissionResourceContribution`, `frontend-component: Capell\FormBuilder\Manifest\FormElementComponentContribution`, `model: Capell\FormBuilder\Manifest\FormModelContribution`, `model: Capell\FormBuilder\Manifest\SubmissionModelContribution`, `route: Capell\FormBuilder\Manifest\FormBuilderPaymentRoutesContribution`, `scheduled-job: Capell\FormBuilder\Manifest\FormBuilderPruneScheduleContribution`.
- Health checks: `Capell\FormBuilder\Health\FormBuilderHealthCheck`.
- Blade views: `packages/form-builder/resources/views/filament/submissions/payload.blade.php`, `packages/form-builder/resources/views/livewire/form-element.blade.php`, `packages/form-builder/resources/views/livewire/form.blade.php`, `packages/form-builder/resources/views/mail/submission-autoresponder.blade.php`, `packages/form-builder/resources/views/mail/submission-notification.blade.php`, `packages/form-builder/resources/views/mail/submission-reply.blade.php`.
- Cache tags: `form-builder`.

## Data Model

- Required tables: `forms`, `submissions`.
- Models: `Form`, `Submission`.
- Core record references in migrations: `sites via site_id`.
- Migration files: `2026_05_10_190849_01_create_form-builder_table.php`, `2026_05_10_190849_02_create_submissions_table.php`, `2026_07_12_000001_add_retention_to_submissions_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; retention is scheduled through `capell:form-builder:prune` (daily).

## Install Impact

- Required packages: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`.
- Admin navigation: declares `admin-resource: FormResourceContribution`, `admin-resource: SubmissionResourceContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: none declared.
- Permissions: `ViewAny:Form`, `View:Form`, `Create:Form`, `Update:Form`, `Delete:Form`, `DeleteAny:Form`, `Restore:Form`, `RestoreAny:Form`, `ForceDelete:Form`, `ForceDeleteAny:Form`, `Reorder:Form`, `ViewAny:Submission`, `View:Submission`, `Reply:Submission`, `Update:Submission`.
- Public routes: loads `routes/payments.php`; registers `FormBuilderPaymentRoutesContribution`.
- Database changes: package migrations are declared.
- Config: `config/capell-form-builder.php`.
- Settings: no package settings declared.
- Queues or schedules: scheduled commands `capell:form-builder:prune (daily)`.
- Cache tags: `form-builder`.
- Commands: `capell:form-builder:prune`.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`.
- Run migrations before opening package resources or public routes.
- Review package configuration before production-like verification: `config/capell-form-builder.php`.
- Review middleware, throttling, signatures, and public-output safety in `routes/payments.php` before exposing routes.
- Register the host scheduler so these declared commands run at their documented frequencies: `capell:form-builder:prune (daily)`.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Custom write integrations must preserve invalidation for `form-builder` cache tags.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Route returns unexpected output | Route cache, middleware, or signed URL setup does not match the package route file | Check the route files listed in `Technical Shape` | Clear route cache and verify middleware before exposing public routes |
| Background work does not run | Queue worker or declared schedule is not active | Check the jobs and scheduled commands listed in `Technical Shape` | Start the queue worker or host scheduler, then run the focused command or package test |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/form-builder`.
2. Run the required setup: `php artisan migrate`.
3. Open the Frontend form output and confirm the public output renders without admin state.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Admin guide](docs/admin-guide.md)
- Configuration files: [`config/capell-form-builder.php`](config/capell-form-builder.php).
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Payments](../payments/README.md).
- Focused tests: `vendor/bin/pest packages/form-builder/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
