## What it does

Form Builder creates site-scoped public forms and a site-scoped submission inbox. Forms support conditional and multi-step fields, calculations, uploads, spam scoring, optional payment hand-off, notifications, autoresponders, and one outbound webhook.

## Setup requirements

Install the package and run its migrations, then place an active form with the Layout Builder **Form Builder form** widget or the frontend Form element and its Key. Public lookup requires both the current site and an active matching form; a key from another site does not render that form.

Keep a queue worker running when staff notifications or submitter autoresponders are configured. Keep Laravel's scheduler running for retention. Configure a private upload disk and directory before accepting files; Form Builder stores uploaded binaries through that filesystem and does not encrypt the file itself. Payments is optional and must be installed and configured separately before Payment fields can redirect to checkout.

Form and Submission resources use separate generated permissions. Non-global administrators see only forms for assigned sites. Submission view, reply, and update access is also resolved per site, including site-team permission assignments. Global/super administrators bypass those site checks.

## Build and publish a form

Open **Marketing > Forms**, select the Site, choose a unique Key for that site, and add fields in the required order. Available schema behaviour includes required and custom Laravel validation rules, conditional visibility, step keys, calculated values, file restrictions, a honeypot, and payment amounts/currency. Hidden conditional fields are not validated or stored.

Guided setup and SiteSpec imports can create a form for you. A spec's `packageData.forms` entries name a key, a name, text, email, textarea or checkbox fields, an optional success message and an optional address for new messages. A spec can hold up to 10 forms of up to 30 fields each, and may not use the key `company_website`, which the hidden honeypot field uses. Each form is created active, keeps its submissions and gets that honeypot field; applying a changed spec updates the form with the same key on that site instead of adding another, and keeps settings the spec does not mention. When a spec changes where a form sends its messages, the activity log records who applied it, with both addresses masked. An entry's `placeOnPage` names a page in the same spec. With Layout Builder installed, the form is added to that page straight after its content as a widget. If other pages share the page's layout, the page gets its own copy first so the form appears only there. A `placeOnPage` that names a page the spec does not have is refused. Without Layout Builder the form is created but not placed, so place it as described in **Put this form on a page**.

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

Open **Marketing > Form submissions** to view the decrypted payload, filter by form/status, mark Read, Spam, or Archived, apply/release a legal hold, and reply. Reply is unavailable for Spam or when no valid submitted Email field exists. It sends immediately through the configured mail transport and changes New to Read after a successful send; Form Builder does not retain the reply body or a delivery receipt.

For operational exports, `capell:form-builder:export-submissions` writes decrypted submissions to standard output or `--path`. `--form` accepts an ID or key; omitting it exports every site. This console command has no admin site-policy filter, so restrict shell access and protect the resulting CSV. Spreadsheet formula prefixes are neutralised, but the file still contains personal data.

## Privacy and retention

Submission payload and metadata are encrypted with the application key. Keep `APP_KEY` stable. Site/form/status/timestamps, legal-hold state, and retention date remain queryable columns. Per-form toggles can omit IP address and user agent, but source URL and referrer are still collected for stored submissions. Uploaded files are protected only by the configured filesystem controls.

The daily `capell:form-builder:prune` schedule is enabled by default and uses 365 days unless configured otherwise. It permanently deletes expired rows and their recognised stored upload paths, while preserving legal holds and future `retention_until` values. Use `--dry-run` before changing the retention period. If scheduling is disabled or Laravel's scheduler is not running, nothing is pruned.

When Privacy Centre is installed, Form Builder exports and erases only the submissions a person sent themselves. The owner is recorded when the submission is saved, from the form's visitor email field (or its first email field), so renaming or retyping fields later does not change it. A submission that merely contains the person's address anywhere else, such as a "friend's email" field or an older submission saved before owners were recorded, is held as an `email_match` review candidate: the request waits until an operator resolves it, and only a `linked` resolution includes it. Erasure also deletes the recognised stored uploads of the erased rows.

Notification and autoresponder emails are recorded as pending in the same save as the submission. If the queue is unavailable, the visitor still sees success, the Form Builder health check reports the waiting submissions, and `capell:form-builder:recover-deliveries` (every five minutes) retries them. Each email is queued at most once.

## After saving your first form

Use **Put this form on a page** from the saved form. It explains same-site placement, activation, draft preview, explicit publication and a public test enquiry. With Layout Builder installed, add a widget that uses the **Form Builder form** component and choose the form in its **Form** setting. The widget shows nothing on a site with no active form of that key. Without Layout Builder, give the developer the displayed handle and site so they can wire the public component first. **View submissions** appears only with submission-view permission. Keep **Store submissions** on when testing the inbox.
