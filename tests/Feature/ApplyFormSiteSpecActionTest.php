<?php

declare(strict_types=1);

use Capell\Core\Data\SiteSpec\CapellSiteSpecData;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\SiteSpec\SiteSpecApplierRegistry;
use Capell\FormBuilder\Actions\ApplyFormSiteSpecAction;
use Capell\FormBuilder\Data\FormFieldData;
use Capell\FormBuilder\Data\FormSettingsData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Models\Form;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Contracts\Translation\Translator;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelData\DataCollection;

uses(CreatesAdminUser::class);

/**
 * The contact form guided setup offers, as Core's presets write it under `packageData.forms`.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function siteSpecContactForm(array $overrides = []): array
{
    return [
        'handle' => 'contact',
        'name' => 'Contact form',
        'placeOnPage' => 'contact',
        'successMessage' => 'Thank you. Your message has been sent.',
        'notify' => 'reception@hartley.test',
        'fields' => [
            ['key' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true],
            ['key' => 'phone', 'label' => 'Phone number', 'type' => 'text', 'required' => false],
            ['key' => 'message', 'label' => 'How can we help?', 'type' => 'textarea', 'required' => true],
        ],
        ...$overrides,
    ];
}

/** @param list<array<string, mixed>> $forms */
function siteSpecWithForms(array $forms): CapellSiteSpecData
{
    return CapellSiteSpecData::from([
        'site' => ['name' => 'Hartley Dental'],
        'theme' => ['key' => 'default'],
        'pages' => [['name' => 'Contact', 'slug' => 'contact', 'title' => 'Contact us', 'pageType' => 'default']],
        'packageData' => ['forms' => $forms],
    ]);
}

/** @return list<FormFieldData> */
function siteSpecFormFields(Form $form): array
{
    $schema = $form->schema;
    $fields = $schema instanceof DataCollection ? $schema->all() : (array) $schema;

    return array_values(array_map(static fn (mixed $field): FormFieldData => $field instanceof FormFieldData ? $field : FormFieldData::from($field), $fields));
}

it('registers the forms site spec applier with Core', function (): void {
    expect(resolve(SiteSpecApplierRegistry::class)->has(ApplyFormSiteSpecAction::KEY))->toBeTrue();
});

it('creates the contact form a site spec asks for, sending its messages to the given address', function (): void {
    $site = Site::factory()->withTranslations()->create();

    resolve(ApplyFormSiteSpecAction::class)->apply(siteSpecWithForms([siteSpecContactForm()]), $site, []);

    $form = Form::query()->whereBelongsTo($site)->where('handle', 'contact')->firstOrFail();
    $fields = siteSpecFormFields($form);

    expect($form->name)->toBe('Contact form')
        ->and($form->is_active)->toBeTrue()
        ->and($form->settings?->notificationEmail)->toBe('reception@hartley.test')
        ->and($form->settings?->successMessage)->toBe('Thank you. Your message has been sent.')
        ->and($form->settings?->storeSubmissions)->toBeTrue()
        ->and($form->settings?->visitorEmailFieldKey)->toBe('email')
        ->and(array_map(static fn (FormFieldData $field): string => $field->key, $fields))->toBe(['name', 'email', 'phone', 'message', ApplyFormSiteSpecAction::HONEYPOT_KEY])
        ->and($fields[1]->type)->toBe(FormFieldType::Email)
        ->and($fields[1]->required)->toBeTrue()
        ->and($fields[2]->required)->toBeFalse()
        ->and($fields[4]->type)->toBe(FormFieldType::Honeypot)
        ->and($fields[4]->label)->toBe(ApplyFormSiteSpecAction::HONEYPOT_LABEL);
});

it("stores the spam trap label once and shows it in the reader's language", function (): void {
    $site = Site::factory()->withTranslations()->create();
    resolve(ApplyFormSiteSpecAction::class)->apply(siteSpecWithForms([siteSpecContactForm()]), $site, []);
    $honeypot = siteSpecFormFields(Form::query()->whereBelongsTo($site)->firstOrFail())[4];

    resolve(Translator::class)->addLines(['form.site_spec_honeypot_label' => 'Laissez ce champ vide'], 'fr', 'capell-form-builder');
    app()->setLocale('fr');

    expect($honeypot->displayLabel())->toBe('Laissez ce champ vide')
        ->and(FormFieldData::resolveLabel('Your name'))->toBe('Your name');
});

it('refuses more than ten forms in one spec and creates none of them', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $forms = array_map(static fn (int $i): array => siteSpecContactForm(['handle' => 'form-' . $i]), range(1, 11));

    expect(fn () => resolve(ApplyFormSiteSpecAction::class)->apply(siteSpecWithForms($forms), $site, []))
        ->toThrow(InvalidArgumentException::class, (string) __('capell-form-builder::form.site_spec.too_many_forms', ['max' => 10]))
        ->and(Form::query()->whereBelongsTo($site)->count())->toBe(0);
});

it('says in plain words why a field key is refused', function (): void {
    $site = Site::factory()->withTranslations()->create();

    expect(fn () => resolve(ApplyFormSiteSpecAction::class)->apply(siteSpecWithForms([siteSpecContactForm(['fields' => [['key' => 'company_website', 'label' => 'Website', 'type' => 'text']]])]), $site, []))
        ->toThrow(InvalidArgumentException::class, (string) __('capell-form-builder::form.site_spec.field_key_reserved', ['form' => 'contact', 'field' => 'company_website']));
});

it('records who changed where a form sends its messages, without storing either address', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $applier = resolve(ApplyFormSiteSpecAction::class);
    $this->actingAsAdmin();
    $actor = $this->authenticatedUser();

    $applier->apply(siteSpecWithForms([siteSpecContactForm()]), $site, []);
    $applier->apply(siteSpecWithForms([siteSpecContactForm(['notify' => 'attacker@elsewhere.test'])]), $site, []);
    $applier->apply(siteSpecWithForms([siteSpecContactForm(['notify' => 'attacker@elsewhere.test'])]), $site, []);

    $form = Form::query()->whereBelongsTo($site)->firstOrFail();
    $entries = Activity::query()->where('event', ApplyFormSiteSpecAction::NOTIFICATION_EMAIL_CHANGED_EVENT)->whereMorphedTo('subject', $form)->orderBy('id')->get();
    $change = $entries->last();
    $stored = json_encode($entries->map->properties, JSON_THROW_ON_ERROR);

    expect($entries)->toHaveCount(2)
        ->and($change?->causer_id)->toBe($actor->getKey())
        ->and($change?->causer_type)->toBe($actor->getMorphClass())
        ->and(data_get($change?->properties, 'old.notification_email'))->toBe('r********@hartley.test')
        ->and(data_get($change?->properties, 'attributes.notification_email'))->toBe('a*******@elsewhere.test')
        ->and(data_get($change?->properties, 'attributes.notification_email_hash'))->toBeString()->not->toBe(data_get($change?->properties, 'old.notification_email_hash'))
        ->and($stored)->not->toContain('reception@hartley.test')
        ->and($stored)->not->toContain('attacker@elsewhere.test');
});

it('updates the same form when a changed spec is applied again, instead of adding a second one', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $applier = resolve(ApplyFormSiteSpecAction::class);

    $applier->apply(siteSpecWithForms([siteSpecContactForm()]), $site, []);
    $applier->apply(siteSpecWithForms([siteSpecContactForm(['notify' => 'owner@hartley.test'])]), $site, []);

    expect(Form::query()->whereBelongsTo($site)->count())->toBe(1)
        ->and(Form::query()->whereBelongsTo($site)->firstOrFail()->settings?->notificationEmail)->toBe('owner@hartley.test');
});

it('keeps settings the spec does not mention when it is applied again', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $applier = resolve(ApplyFormSiteSpecAction::class);

    $applier->apply(siteSpecWithForms([siteSpecContactForm()]), $site, []);

    $form = Form::query()->whereBelongsTo($site)->firstOrFail();
    $form->update(['settings' => FormSettingsData::from([...$form->settings?->toArray() ?? [], 'autoresponder_subject' => 'We have your message'])]);

    $applier->apply(siteSpecWithForms([siteSpecContactForm(['notify' => null])]), $site, []);

    $settings = $form->refresh()->settings;

    expect($settings?->autoresponderSubject)->toBe('We have your message')
        ->and($settings?->notificationEmail)->toBe('reception@hartley.test');
});

it('keeps submissions without sending email when the spec names no address', function (): void {
    $site = Site::factory()->withTranslations()->create();

    resolve(ApplyFormSiteSpecAction::class)->apply(siteSpecWithForms([siteSpecContactForm(['notify' => null])]), $site, []);

    $settings = Form::query()->whereBelongsTo($site)->firstOrFail()->settings;

    expect($settings?->notificationEmail)->toBeNull()
        ->and($settings?->storeSubmissions)->toBeTrue();
});

it('refuses a form it cannot build, so the spec apply is rolled back', function (array $overrides): void {
    $site = Site::factory()->withTranslations()->create();

    resolve(ApplyFormSiteSpecAction::class)->apply(siteSpecWithForms([siteSpecContactForm($overrides)]), $site, []);
})->with([
    'no handle' => [['handle' => '']],
    'no fields' => [['fields' => []]],
    'unsupported field type' => [['fields' => [['key' => 'cv', 'label' => 'CV', 'type' => 'file']]]],
    'repeated field key' => [['fields' => [['key' => 'email', 'label' => 'Email', 'type' => 'email'], ['key' => 'email', 'label' => 'Again', 'type' => 'email']]]],
    'notify is not an address' => [['notify' => 'reception']],
    'field uses the honeypot key' => [['fields' => [['key' => 'company_website', 'label' => 'Company website', 'type' => 'text']]]],
    'too many fields' => [['fields' => array_map(static fn (int $i): array => ['key' => 'field_' . $i, 'label' => 'Field ' . $i, 'type' => 'text'], range(1, 31))]],
    'name too long' => [['name' => str_repeat('a', 256)]],
    'label too long' => [['fields' => [['key' => 'name', 'label' => str_repeat('a', 256), 'type' => 'text']]]],
    'success message too long' => [['successMessage' => str_repeat('a', 1001)]],
])->throws(InvalidArgumentException::class);

it('leaves forms on other sites alone', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $other = Site::factory()->withTranslations()->create();
    $applier = resolve(ApplyFormSiteSpecAction::class);

    $applier->apply(siteSpecWithForms([siteSpecContactForm(['notify' => 'other@hartley.test'])]), $other, []);
    $applier->apply(siteSpecWithForms([siteSpecContactForm()]), $site, []);

    expect(Form::query()->whereBelongsTo($other)->firstOrFail()->settings?->notificationEmail)->toBe('other@hartley.test')
        ->and(Form::query()->whereBelongsTo($site)->firstOrFail()->settings?->notificationEmail)->toBe('reception@hartley.test');
});

it('refuses a form whose placeOnPage names a page the spec does not have, before creating anything', function (): void {
    $site = Site::factory()->withTranslations()->create();

    expect(fn () => resolve(ApplyFormSiteSpecAction::class)->apply(siteSpecWithForms([siteSpecContactForm(['placeOnPage' => 'enquiries'])]), $site, []))
        ->toThrow(InvalidArgumentException::class, (string) __('capell-form-builder::form.site_spec.unknown_page', ['form' => 'contact', 'page' => 'enquiries']))
        ->and(Form::query()->whereBelongsTo($site)->exists())->toBeFalse();
});

it('still creates the form when Layout Builder is not installed to place it', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $page = Page::factory()->site($site)->create();
    $layoutId = $page->layout_id;

    resolve(ApplyFormSiteSpecAction::class)->apply(siteSpecWithForms([siteSpecContactForm()]), $site, ['contact' => $page]);

    expect(Form::query()->whereBelongsTo($site)->where('handle', 'contact')->where('is_active', true)->exists())->toBeTrue()
        ->and($page->refresh()->layout_id)->toBe($layoutId);
});
