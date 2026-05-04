# Forms

Status: **Available, schema-owning** · Kind: **package** · Tier: **premium** · Bundle: **forms** · Contexts: **admin, frontend** · Product group: **Capell Forms**

This page is the consolidated implementation overview for the Forms package. It is extracted from the package README, service providers, migrations, config files, routes, resources, models, actions, and the shared Capell ERD notes where available.

## What This Plugin Adds

Forms adds form definitions, encrypted submissions, frontend Livewire rendering, validation, and submission status handling to Capell.

- Form and submission models.
- Frontend Livewire form component.
- Actions for validation, submission creation, archiving, read state, and spam marking.
- FormSubmitted event.
- Configurable submission storage and request metadata collection.

## Developer Notes

Keeps form schema, settings, submission payload, and metadata in data objects and casts so form handling remains typed across layers.

- FormsServiceProvider registers the package.
- Config file: capell-forms.php.
- Migrations create forms and submissions.
- Models: Form and Submission.
- Livewire component: FormComponent.
- EncryptedDataCast protects stored submission data.

## Operational Notes

Lets editors collect responses from structured websites while keeping submission review inside the Capell admin workflow.

- Adds forms and submissions tables.
- Adds frontend Livewire form component.
- Adds config keys for storing submissions, IP address collection, and user agent collection.
- No routes are visible in this package.

## Data And Retention

- forms belongs to sites and stores handle, schema, settings, and active state.
- submissions belongs to forms and sites and stores payload, meta, status, and submitted_at.
- Submission payload and metadata are represented by data objects.
- Deletion and retention rules should be verified against the host application policy.

## Screenshot Plan

- Forms admin index.
- Create/edit form schema screen.
- Submissions index.
- Frontend form output.
- Submission detail view.

## Pitfalls

- Disable IP/user agent collection where privacy policy requires it.
- Run migrations before rendering form components.
- Validate field schema before accepting public submissions.

## Verification

- Run `vendor/bin/pest packages/forms/tests` when package tests exist.
- Run the relevant host-app migration or package install flow in a disposable database.
- Open the listed admin or frontend surface and compare it with the screenshot plan.

## Package Manifest

- Composer name: `capell-app/forms`
- Product group: Capell Forms
- Kind: package
- Tier: premium
- Bundle: forms
- Contexts: `admin`, `frontend`
- Requires: `capell-app/core`, `capell-app/admin`, `capell-app/frontend`
- Optional dependencies: None listed.

## Admin Surfaces

- None proven in this package directory.

## Commands

- None proven in this package directory.

## Routes And Config

- Config: packages/forms/config/capell-forms.php

## Permissions And Gates

- None proven in this package directory.

## Migrations

- Migration: create_forms_table.php
- Migration: create_submissions_table.php

## ERD Excerpt

```mermaid
erDiagram
    SITES ||--o{ FORMS : owns
    FORMS ||--o{ SUBMISSIONS : receives
    SITES ||--o{ SUBMISSIONS : scopes

    FORMS {
        bigint id PK
        bigint site_id FK
        string handle
        json schema
        json settings
        boolean is_active
    }

    SUBMISSIONS {
        bigint id PK
        bigint form_id FK
        bigint site_id FK
        longtext payload
        longtext meta
        string status
        timestamp submitted_at
    }
```

## Screenshot Automation

Deployment should read [screenshots.json](screenshots.json), install the package with demo data, resolve each admin surface or frontend URL, and write images to `public/docs/screenshots/packages/forms`.

- Forms admin index.
- Create/edit form schema screen.
- Submissions index.
- Frontend form output.
- Submission detail view.
