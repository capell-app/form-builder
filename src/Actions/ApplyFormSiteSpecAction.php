<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Contracts\SiteSpec\SiteSpecApplier;
use Capell\Core\Data\SiteSpec\CapellSiteSpecData;
use Capell\Core\Data\SiteSpec\CapellSiteSpecPageData;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormSettingsData;
use Capell\FormBuilder\Data\SiteSpecFormData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Override;

/**
 * Creates the forms a SiteSpec asks for under `packageData.forms`, such as the contact form guided
 * setup offers. A form is matched on the site by its handle, so applying a changed spec updates it
 * rather than adding a second one. Every form gets a hidden spam trap and keeps its submissions.
 * A form with `placeOnPage` is put on that spec page as a Layout Builder widget when Layout Builder
 * is installed; a `placeOnPage` naming a page the spec does not have is refused.
 *
 * A spec can come from an import or an agent, so a change to where a form sends its messages is
 * written to the activity log with both addresses masked and hashed.
 */
final class ApplyFormSiteSpecAction implements SiteSpecApplier
{
    public const string KEY = 'forms';

    public const string HONEYPOT_KEY = SiteSpecFormData::HONEYPOT_KEY;

    /** Stored as a translation key so the label follows the reader's locale. */
    public const string HONEYPOT_LABEL = 'capell-form-builder::form.site_spec_honeypot_label';

    public const string NOTIFICATION_EMAIL_CHANGED_EVENT = 'site_spec_notification_email_changed';

    #[Override]
    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @param  array<string, Page>  $pagesBySlug
     *
     * @throws InvalidArgumentException when an entry is not a usable form; the whole spec apply is rolled back
     */
    #[Override]
    public function apply(CapellSiteSpecData $spec, Site $site, array $pagesBySlug): void
    {
        $entries = $spec->packageData[self::KEY] ?? [];
        $entries = is_array($entries) ? $entries : [];

        if (count($entries) > SiteSpecFormData::MAX_FORMS) {
            throw new InvalidArgumentException((string) __('capell-form-builder::form.site_spec.too_many_forms', ['max' => SiteSpecFormData::MAX_FORMS]));
        }

        $forms = array_map(static fn (mixed $entry): SiteSpecFormData => SiteSpecFormData::fromSpec(is_array($entry) ? $entry : []), array_values($entries));

        $specPageSlugs = array_map(static fn (CapellSiteSpecPageData $page): string => $page->slug, $spec->pages);

        foreach ($forms as $form) {
            if ($form->placeOnPage !== null && ! in_array($form->placeOnPage, $specPageSlugs, true)) {
                throw new InvalidArgumentException((string) __('capell-form-builder::form.site_spec.unknown_page', ['form' => $form->handle, 'page' => $form->placeOnPage]));
            }
        }

        foreach ($forms as $form) {
            $model = $this->upsert($form, $site);
            $page = $form->placeOnPage !== null ? ($pagesBySlug[$form->placeOnPage] ?? null) : null;

            if ($page instanceof Page) {
                // Without Layout Builder the form is created but not placed; Put this form on a page explains the next step.
                PlaceFormOnPageAction::run($model, $page);
            }
        }
    }

    private function upsert(SiteSpecFormData $data, Site $site): Form
    {
        $fields = [
            ...$data->fields,
            new FormFieldData(key: self::HONEYPOT_KEY, label: self::HONEYPOT_LABEL, type: FormFieldType::Honeypot),
        ];

        $form = Form::query()->firstOrNew(['site_id' => $site->getKey(), 'handle' => $data->handle]);
        // Settings the spec does not mention, such as an autoresponder or webhook set up later, are kept.
        $existing = $form->settings instanceof FormSettingsData ? $form->settings->toArray() : [];
        $previousEmail = is_string($existing['notification_email'] ?? null) ? $existing['notification_email'] : null;
        $notificationEmail = $data->notify ?? $previousEmail;

        $form->fill([
            'name' => $data->name,
            'schema' => array_map(static fn (FormFieldData $field): array => $field->toArray(), $fields),
            'settings' => FormSettingsData::from([
                ...$existing,
                'success_message' => $data->successMessage ?? ($existing['success_message'] ?? null),
                'store_submissions' => true,
                'notification_email' => $notificationEmail,
                'visitor_email_field_key' => $data->visitorEmailFieldKey(),
            ]),
            'is_active' => true,
        ])->save();

        if ($notificationEmail !== null && mb_strtolower($notificationEmail) !== mb_strtolower((string) $previousEmail)) {
            $this->recordNotificationEmailChange($form, $previousEmail, $notificationEmail);
        }

        return $form;
    }

    private function recordNotificationEmailChange(Form $form, ?string $from, string $to): void
    {
        $actor = auth()->user();

        activity()
            ->performedOn($form)
            ->causedBy($actor instanceof Model ? $actor : null)
            ->event(self::NOTIFICATION_EMAIL_CHANGED_EVENT)
            ->withProperties([
                'source' => 'site-spec',
                'old' => ['notification_email' => $this->masked($from), 'notification_email_hash' => $this->hashed($from)],
                'attributes' => ['notification_email' => $this->masked($to), 'notification_email_hash' => $this->hashed($to)],
            ])
            ->log((string) __('capell-form-builder::form.site_spec.notification_email_changed', ['form' => $form->handle]));
    }

    /** `reception@hartley.test` becomes `r********@hartley.test`, enough to recognise without exposing it. */
    private function masked(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1) . str_repeat('*', max(1, mb_strlen($local) - 1)) . '@' . $domain;
    }

    /** A keyed hash, so a later address can be compared with this one without storing it. */
    private function hashed(?string $email): ?string
    {
        $key = config('app.key');

        return $email === null ? null : hash_hmac('sha256', mb_strtolower(trim($email)), is_string($key) ? $key : '');
    }
}
