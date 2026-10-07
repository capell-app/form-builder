---
name: capell-form-builder-development
description: Form definitions, encrypted submissions, frontend Livewire rendering, validation, and submission states. Use when editing Capell FormBuilder definitions, validation, submissions, or frontend rendering.
---

# Capell FormBuilder

Form definitions, encrypted submissions, frontend Livewire rendering, validation, and submission states.

## Look

- `packages/form-builder/src`
- `packages/form-builder/docs`
- `packages/form-builder/README.md`

## Rules

- Keep submissions encrypted and status changes action-driven.
- Validation and spam/read/archive behaviour belongs in Actions.
- Frontend Livewire should render form-builder, not own submission policy.
- Verify customisations in the consuming application's test suite.
