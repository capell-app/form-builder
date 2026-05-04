---
name: capell-forms-development
description: Use when editing Capell Forms definitions, validation, submissions, or frontend rendering.
---

# Capell Forms

Form definitions, encrypted submissions, frontend Livewire rendering, validation, and submission states.

## Look

- `packages/forms/src`
- `packages/forms/docs`
- `packages/forms/README.md`

## Rules

- Keep submissions encrypted and status changes action-driven.
- Validation and spam/read/archive behaviour belongs in Actions.
- Frontend Livewire should render forms, not own submission policy.
- Run `vendor/bin/pest packages/forms/tests`.
