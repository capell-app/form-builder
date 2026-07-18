## What it does

Form Builder creates site-scoped public forms and a site-scoped submission inbox. Forms support conditional and multi-step fields, calculations, uploads, spam scoring, optional payment hand-off, notifications, autoresponders, and one outbound webhook.

## Setup requirements

Install the package and run its migrations, then add the frontend Form element with an active form's Handle. Public lookup requires both the current site and an active matching form; a handle from another site does not render that form.

Keep a queue worker running when staff notifications or submitter autoresponders are configured. Keep Laravel's scheduler running for retention. Configure a private upload disk and directory before accepting files; Form Builder stores uploaded binaries through that filesystem and does not encrypt the file itself. Payments is optional and must be installed and configured separately before Payment fields can redirect to checkout.

Form and Submission resources use separate generated permissions. Non-global administrators see only forms for assigned sites. Submission view, reply, and update access is also resolved per site, including site-team permission assignments. Global/super administrators bypass those site checks.

## Build and publish a form

Open **Marketing Studio > Forms**, select the Site, choose a unique Handle for that site, and add fields in the required order. Available schema behaviour includes required and custom Laravel validation rules, conditional visibility, step keys, calculated values, file restrictions, a honeypot, and payment amounts/currency. Hidden conditional fields are not validated or stored.

Only an active form renders publicly. Test the complete published form after changing keys, conditions, calculations, upload rules, or steps; stored payloads retain the field keys that existed when each visitor submitted.

Public submission attempts are rate-limited by form, site, submitted email, and IP address (12 attempts per 60 seconds by default). Heuristic spam scoring is enabled by default. A triggered honeypot is stored as Spam with an empty payload when storage is enabled, and spam submissions do not trigger the normal event, email, autoresponder, webhook, or payment flow. Optional Turnstile support is configured in `capell-form-builder`; a missing token, provider error, timeout, or rejected response counts as a failed spam check rather than allowing the submission through.

## Storage and delivery choices

**Store submissions** is a significant workflow boundary:

- when enabled, Form Builder validates and encrypts a Submission row, stores accepted uploads, emits the submission event, queues configured emails, calls the webhook, and can continue to Payments;
- when disabled, no Submission row or uploaded binary is kept. A successful non-spam submission emits only the in-memory event. The built-in notification, autoresponder, webhook, inbox, reply, retention, privacy, and payment workflows do not run.

The Notification email receives a queued message for each non-spam stored submission. An autoresponder requires both Subject and Body plus a valid submitted value in the first Email field. Queueing failures are logged and do not roll back the stored submission. There is no Form Builder delivery log or retry button, so monitor the application queue and mail transport.

Webhook delivery is synchronous in the visitor's submission request after the row is stored. It sends the form identity, decrypted payload, submission status/time, source URL, and referrer. HTTPS is required by default; redirects, unresolved hosts, and private/reserved targets are blocked, and DNS is pinned for the request. Failures are redacted in logs and swallowed so the visitor still sees success. There is no signature, automatic retry, delivery record, or admin replay. The receiving endpoint must authenticate by a separate agreed mechanism if required and should deduplicate by submission ID. Relaxing the insecure/private URL config weakens the server-side request boundary.

Success redirect URLs are administrator-supplied and used after submission. Enter only a trusted destination and test it; this package does not provide an allow-list for that form setting.

## Payments

A Payment field redirects only after a stored, non-spam Submission has been created and only when the Payments integration and checkout table are available. Fixed configured cents take precedence over a submitted amount; currency must be a three-letter code. The hand-off uses a temporary signed local route and Payments validates return destinations.

The Submission existing in the inbox means the form was accepted, not that payment completed. Payment completion, retries, refunds, and reconciliation belong to Payments. If Payments is unavailable, the form submission succeeds without a checkout redirect.

## Triage and reply

Open **Marketing Studio > Form submissions** to view the decrypted payload, filter by form/status, mark Read, Spam, or Archived, apply/release a legal hold, and reply. Reply is unavailable for Spam or when no valid submitted Email field exists. It sends immediately through the configured mail transport and changes New to Read after a successful send; Form Builder does not retain the reply body or a delivery receipt.

For operational exports, `capell:form-builder:export-submissions` writes decrypted submissions to standard output or `--path`. `--form` accepts an ID or handle; omitting it exports every site. This console command has no admin site-policy filter, so restrict shell access and protect the resulting CSV. Spreadsheet formula prefixes are neutralised, but the file still contains personal data.

## Privacy and retention

Submission payload and metadata are encrypted with the application key. Keep `APP_KEY` stable. Site/form/status/timestamps, legal-hold state, and retention date remain queryable columns. Per-form toggles can omit IP address and user agent, but source URL and referrer are still collected for stored submissions. Uploaded files are protected only by the configured filesystem controls.

The daily `capell:form-builder:prune` schedule is enabled by default and uses 365 days unless configured otherwise. It permanently deletes expired rows and their recognised stored upload paths, while preserving legal holds and future `retention_until` values. Use `--dry-run` before changing the retention period. If scheduling is disabled or Laravel's scheduler is not running, nothing is pruned.

When Privacy Center is installed, Form Builder matches subjects by their email against payload keys containing `email`, exports matching submissions, and deletes matching rows on erasure. That matching is installation-wide and does not establish identity beyond the email value. The eraser does not run the retention action and therefore does not remove uploaded files referenced by those rows; include the upload disk in the site's erasure procedure.
