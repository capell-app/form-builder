# Changelog

All notable changes to `capell-app/form-builder` will be documented in this file.

## Unreleased

- Added submission CSV export via `capell:form-builder:export-submissions` and per-form outbound webhook dispatch for successful stored submissions.
- Persisted uploaded form files to a configured disk and stored server-verified disk/path references in encrypted submission payloads.
- Added form success redirect settings and queued submitter autoresponder emails for successful stored submissions.
- Added frontend multi-step form rendering with step progress, previous/continue controls, and per-step validation before final submission.
- Memoized site-scoped submission permission lookups per actor and ability set to reduce repeated admin list/table/filter queries.

## 2026-06-03

### Added

- Real `FormBuilderHealthCheck` diagnostics: probes the `forms` and `submissions` storage tables, confirms spam scoring is enabled, and verifies submission `payload`/`meta` are cast through `EncryptedDataCast` (encrypted at rest). Replaces the previous contract-only stub behind the three declared health checks.

### Changed

- Rewrote the marketplace summary, listing description, and `composer.json` description to reflect shipped functionality (site-scoped forms, encrypted submissions, triage inbox, one-click email replies) instead of an over-claiming feature list.
- Surfaced the existing admin index, submissions index, submission detail, and frontend output screenshots (light + dark) in the marketplace manifest.

### Fixed

- Spam submissions no longer resolve a reply-to address, so the admin Reply action and `Reply to` column are hidden for spam entries and `ReplyToSubmissionAction` refuses to email an attacker-supplied address.

### Prepared

- Package metadata and documentation for ongoing Capell 4.x package work.
