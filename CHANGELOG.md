# Changelog

All notable changes to `capell-app/form-builder` will be documented in this file.

## Unreleased

- Throttle now keys on form, site and IP only, with a separate per-recipient cap across every email field, so rotating the address no longer earns a fresh budget.
- The Turnstile widget now renders in the public form and its token is submitted; the provider also gates submissions when heuristic scoring is off.
- Spam submissions no longer store uploads; privacy erasure, form deletion, pruning and admin deletion remove stored upload files.
- Submissions table gains policy-gated Delete, bulk delete (skipping legal holds), CSV export and an authorised file download, with new `Delete:Submission`, `DeleteAny:Submission` and `Export:Submission` permissions. Spam is hidden by default and left out of exports; the CSV streams.
- Webhooks are HMAC-signed and no longer include storage paths. Empty accepted file types fall back to a safe document and image allow-list. Success redirects must be relative or https. Notification email accepts several recipients.
- Added submission CSV export via `capell:form-builder:export-submissions` and per-form outbound webhook dispatch for successful stored submissions.
- Persisted uploaded form files to a configured disk and stored server-verified disk/path references in encrypted submission payloads.
- Added form success redirect settings and queued submitter autoresponder emails for successful stored submissions.
- Added frontend multi-step form rendering with step progress, previous/continue controls, and per-step validation before final submission.
- Memoized site-scoped submission permission lookups per actor and ability set to reduce repeated admin list/table/filter queries.
- Added configurable external spam protection providers, including a Turnstile verifier that contributes failed provider checks to the existing spam score.

## 2026-06-03

### Added

- Real `FormBuilderHealthCheck` diagnostics: probes the `forms` and `submissions` storage tables, confirms spam scoring is enabled, and verifies submission `payload`/`meta` are cast through `EncryptedDataCast` (encrypted at rest). Replaces the previous contract-only stub behind the three declared health checks.

### Changed

- Rewrote the marketplace summary, listing description, and `composer.json` description to reflect shipped functionality (site-scoped forms, encrypted submissions, triage inbox, one-click email replies) instead of an over-claiming feature list.
- Surfaced the existing admin index, submissions index, submission detail, and frontend output screenshots (light + dark) in the marketplace manifest.

### Fixed

- Spam submissions no longer resolve a reply-to address, so the admin Reply action and `Reply to` column are hidden for spam entries and `ReplyToSubmissionAction` refuses to email an attacker-supplied address.

### Prepared

- Package metadata and documentation for ongoing Capell 0.0.x package work.
