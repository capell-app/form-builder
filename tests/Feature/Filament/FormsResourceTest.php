<?php

declare(strict_types=1);

use Capell\Admin\Filament\Pages\SettingsPage;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\BuildFormPlacementAction;
use Capell\FormBuilder\Actions\ResolveVisibleFormFieldsAction;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\CreateForm;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\EditForm;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\ListForms;
use Capell\FormBuilder\Livewire\FormComponent;
use Capell\FormBuilder\Livewire\FormElementComponent;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\State\FrontendState;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;

use function Pest\Livewire\livewire;

use Spatie\Permission\Models\Permission;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    test()->actingAsAdmin();
});

it('places forms in the marketing navigation group', function (): void {
    expect(FormResource::getNavigationGroup())->toBe((string) __('capell-admin::navigation.group_marketing'))
        ->and(FormResource::getNavigationParentItem())->toBeNull()
        ->and(FormResource::getNavigationLabel())->toBe((string) __('capell-form-builder::navigation.forms'))
        ->and(FormResource::getPages())->toHaveKeys(['index', 'create', 'edit']);
});

it('requires form permission to access forms', function (): void {
    test()->actingAsUser();

    expect(FormResource::canAccess())->toBeFalse()
        ->and(FormResource::canViewAny())->toBeFalse();
});

it('allows users with form view permission to access forms', function (): void {
    Permission::findOrCreate('ViewAny:Form');
    test()->actingAs(test()->createUserWithPermission('ViewAny:Form'));

    expect(FormResource::canAccess())->toBeTrue()
        ->and(FormResource::canViewAny())->toBeTrue();
});

it('lists form definitions in the admin resource', function (): void {
    $forms = Form::factory()
        ->count(2)
        ->create();

    livewire(ListForms::class)
        ->assertSuccessful()
        ->assertCountTableRecords(2)
        ->assertCanSeeTableRecords($forms);
});

it('mounts create and edit form schemas', function (): void {
    $form = Form::factory()->create();

    livewire(CreateForm::class)
        ->assertSuccessful()
        ->assertSchemaExists('form');

    livewire(EditForm::class, [
        'record' => $form->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaExists('form');
});

it('offers only current Email fields by stable key when authoring a form', function (): void {
    Repeater::fake();
    $editor = livewire(CreateForm::class)
        ->fillForm(['schema' => [
            ['key' => 'colleague', 'label' => 'Colleague', 'type' => 'email'],
            ['key' => 'visitor', 'label' => 'Visitor', 'type' => 'email'],
            ['key' => 'notes', 'label' => 'Notes', 'type' => 'text'],
        ]])
        ->assertFormFieldExists('settings.visitor_email_field_key', fn (Select $field): bool => $field->getOptions() === [
            'colleague' => 'Colleague (colleague)',
            'visitor' => 'Visitor (visitor)',
        ]);

    $editor->set('data.schema.0.type', 'text')
        ->assertFormFieldExists('settings.visitor_email_field_key', fn (Select $field): bool => $field->getOptions() === [
            'visitor' => 'Visitor (visitor)',
        ]);

    $editor->set('data.schema', [])
        ->assertFormFieldExists('settings.visitor_email_field_key', fn (Select $field): bool => $field->getOptions() === []);
});

it('persists and clears the visitor Email field selection', function (): void {
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'colleague', 'label' => 'Colleague', 'type' => 'email'],
            ['key' => 'visitor', 'label' => 'Visitor', 'type' => 'email'],
        ],
    ]);

    $editor = livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->fillForm(['settings.visitor_email_field_key' => 'visitor'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($form->refresh()->settings?->visitorEmailFieldKey)->toBe('visitor');

    $editor->fillForm(['settings.visitor_email_field_key' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($form->refresh()->settings?->visitorEmailFieldKey)->toBeNull();
});

it('validates calculation grammar when authoring a form', function (): void {
    Repeater::fake();
    $form = Form::factory()->create();

    livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->fillForm(['schema' => [[
            'key' => 'total',
            'label' => 'Total',
            'type' => 'calculation',
            'calculation_expression' => '2 *',
        ]]])
        ->call('save')
        ->assertHasFormErrors(['schema.0.calculation_expression'])
        ->fillForm(['schema' => [[
            'key' => 'total',
            'label' => 'Total',
            'type' => 'calculation',
            'calculation_expression' => '.5 * -quantity',
        ]]])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('shows authoring settings for the selected field type', function (FormFieldType $type, array $fields): void {
    Repeater::fake();
    $form = Form::factory()->create();
    $editor = livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->fillForm(['schema' => [['key' => 'example', 'label' => 'Example', 'type' => $type->value]]]);

    foreach ($fields as $field) {
        $editor->assertFormFieldVisible('schema.0.' . $field);
    }
})->with([
    [FormFieldType::Calculation, ['calculation_expression']],
    [FormFieldType::Select, ['options']],
    [FormFieldType::File, ['accepted_file_types', 'max_file_size_kilobytes']],
    [FormFieldType::Payment, ['payment_amount_cents', 'payment_currency']],
]);

it('displays the translated form key helper on the authoring screen', function (): void {
    expect(resolve(Translator::class)->has('capell-form-builder::form.admin.fields.handle_help'))->toBeTrue();
    livewire(CreateForm::class)
        ->assertSuccessful()
        ->assertSee(__('capell-form-builder::form.admin.fields.handle_help'))
        ->assertFormFieldExists('handle', function (TextInput $field): bool {
            $helper = $field->getChildSchema('below_content')?->getComponents()[0] ?? null;

            return $field->getLabel() === __('capell-form-builder::form.admin.fields.handle')
                && $helper instanceof Text
                && $helper->getContent() === __('capell-form-builder::form.admin.fields.handle_help');
        });
});

it('validates the form key using its existing authoring rules', function (string $key, bool $valid): void {
    $fields = FormResource::form(Schema::make(new SettingsPage))->getFlatFields();
    $field = $fields['handle'];
    throw_unless($field instanceof TextInput, RuntimeException::class);

    expect(Validator::make(['handle' => $key], ['handle' => $field->getValidationRules()])->passes())->toBe($valid);
})->with([
    'dashes' => ['newsletter-signup', true],
    'underscores' => ['newsletter_signup', true],
    'uppercase and numbers' => ['Newsletter_2026', true],
    'unicode letters' => ['lettre_é', true],
    'spaces' => ['newsletter signup', false],
    'punctuation' => ['newsletter.signup', false],
    'required' => ['', false],
    'maximum length' => [str_repeat('a', 256), false],
]);

it('hands a saved form to placement and a scoped submissions destination without publishing', function (): void {
    $form = Form::factory()->create(['handle' => 'first-enquiry', 'is_active' => true]);
    $before = Page::query()->count();

    $editor = livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionExists('placement')
        ->mountAction('placement')
        ->assertActionMounted('placement');
    $instance = $editor->instance();
    throw_unless($instance instanceof EditForm, RuntimeException::class, 'Expected the saved form editor.');
    $content = $instance->getMountedAction()?->getModalContent();
    throw_unless($content instanceof View, RuntimeException::class, 'Expected placement guidance.');
    $html = $content->render();
    expect($html)->toContain('first-enquiry', 'data-form-placement', 'data-form-submissions');

    expect(Page::query()->count())->toBe($before);
});

it('explains activation before offering an inactive form for placement', function (): void {
    $form = Form::factory()->create(['handle' => 'inactive-enquiry', 'is_active' => false]);

    $editor = livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->mountAction('placement')
        ->assertActionMounted('placement');
    $instance = $editor->instance();
    throw_unless($instance instanceof EditForm, RuntimeException::class, 'Expected the saved form editor.');
    $content = $instance->getMountedAction()?->getModalContent();
    throw_unless($content instanceof View, RuntimeException::class, 'Expected placement guidance.');
    $html = $content->render();
    expect($html)->toContain('data-form-placement-unavailable')->not->toContain('data-form-placement-handle');
});

it('rechecks placement permission and reports disabled submission storage', function (): void {
    $form = Form::factory()->create(['settings' => ['store_submissions' => false]]);
    $placement = BuildFormPlacementAction::run($form);
    expect($placement->storesSubmissions)->toBeFalse();

    $this->actingAsUser();
    expect(fn (): mixed => BuildFormPlacementAction::run($form))
        ->toThrow(AuthorizationException::class);
});

it('uses the saved placement handle for a real public test enquiry and stored submission', function (): void {
    $form = Form::factory()->create([
        'handle' => 'saved-placement-test',
        'schema' => [['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
        'settings' => ['store_submissions' => true],
    ]);
    livewire(EditForm::class, ['record' => $form->getRouteKey()])->call('save')->assertHasNoFormErrors();
    $placement = BuildFormPlacementAction::run($form->refresh());
    $site = $form->site;
    throw_unless($site instanceof Site, RuntimeException::class);
    $origin = parse_url(url('/'));
    $site->siteDomains()->update([
        'domain' => $origin['host'] ?? 'localhost', 'scheme' => $origin['scheme'] ?? 'http',
        'path' => '/', 'port' => $origin['port'] ?? null,
    ]);
    app()->instance(FrontendContextReader::class, (new FrontendState)->withSite($site));
    Frontend::clearResolvedInstance(FrontendContextReader::class);
    auth()->logout();

    $element = livewire(FormElementComponent::class, ['handle' => $placement->handle])
        ->call('loadForm')->assertSee('Email');
    $instance = $element->instance();
    throw_unless($instance instanceof FormElementComponent, RuntimeException::class, 'Expected the public form element.');
    $reference = $instance->formReference;
    expect($reference)->not->toBe('');
    livewire(FormComponent::class, ['formReference' => $reference])
        ->set('data.email', 'test-enquiry@example.test')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $submission = Submission::query()->sole();
    $payload = $submission->payload;
    throw_unless($payload instanceof SubmissionPayloadData, RuntimeException::class, 'Expected a structured submission payload.');
    expect($submission->form_id)->toBe($form->id)
        ->and($payload->values)->toBe(['email' => 'test-enquiry@example.test']);
});

it('denies the placement handoff for a form on an unassigned site', function (): void {
    $form = Form::factory()->create();
    Permission::findOrCreate('Update:Form');
    $this->actingAs($this->createUserWithPermission('Update:Form'));

    expect(fn (): mixed => BuildFormPlacementAction::run($form))
        ->toThrow(AuthorizationException::class);
});

it('redirects a newly created form into its placement handoff', function (): void {
    Repeater::fake();
    $existing = Form::factory()->create();
    $creator = livewire(CreateForm::class)
        ->fillForm([
            'site_id' => $existing->site_id,
            'name' => 'First saved enquiry', 'handle' => 'created-enquiry', 'is_active' => true,
            'schema' => [['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
            'settings' => ['store_submissions' => true],
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    $saved = Form::query()->where('handle', 'created-enquiry')->sole();
    $creator->assertRedirect(FormResource::getUrl('edit', ['record' => $saved, 'action' => 'placement']));
});

it('still requires a complete condition when an editor deliberately adds one', function (): void {
    Repeater::fake();
    $existing = Form::factory()->create();
    livewire(CreateForm::class)
        ->fillForm([
            'site_id' => $existing->site_id,
            'name' => 'Conditional enquiry', 'handle' => 'conditional-enquiry',
            'schema' => [[
                'key' => 'email', 'label' => 'Email', 'type' => 'email',
                'visibility_conditions' => [['field_key' => '', 'operator' => null]],
            ]],
        ])
        ->call('create')
        ->assertHasFormErrors(['schema.0.visibility_conditions.0.field_key', 'schema.0.visibility_conditions.0.operator']);

    expect(Form::query()->where('handle', 'conditional-enquiry')->exists())->toBeFalse();
});

it('rejects duplicate answer keys across the complete schema and retains the entered form', function (?string $firstStep, ?string $secondStep, array $conditions): void {
    Repeater::fake();
    $existing = Form::factory()->create();
    $schema = [
        ['key' => 'answer', 'label' => 'Primary answer', 'type' => 'email', 'step_key' => $firstStep],
        ['key' => 'answer', 'label' => 'Other answer', 'type' => 'number', 'step_key' => $secondStep, 'visibility_conditions' => $conditions],
    ];

    livewire(CreateForm::class)
        ->fillForm([
            'site_id' => $existing->site_id,
            'name' => 'Duplicate answers', 'handle' => 'duplicate-answers',
            'schema' => $schema,
        ])
        ->call('create')
        ->assertHasFormErrors(['schema.0.key', 'schema.1.key'])
        ->assertSet('data.name', 'Duplicate answers')
        ->assertSet('data.schema.0.key', 'answer')
        ->assertSet('data.schema.1.key', 'answer')
        ->assertSet('data.schema.1.label', 'Other answer')
        ->assertSee(__('capell-form-builder::form.admin.duplicate_field_key', ['fields' => 'Primary answer, Other answer']));

    expect(Form::query()->where('handle', 'duplicate-answers')->exists())->toBeFalse();
})->with([
    'same step' => [null, null, []],
    'different steps' => ['Contact', 'Details', []],
    'conditional branch' => ['Contact', 'Details', [['field_key' => 'answer', 'operator' => 'equals', 'value' => 'yes']]],
]);

it('requires deliberate correction of legacy duplicate keys without rewriting stored answers', function (): void {
    Repeater::fake();
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'answer', 'label' => 'First answer', 'type' => 'text'],
            ['key' => 'answer', 'label' => 'Second answer', 'type' => 'text'],
        ],
    ]);
    $submission = Submission::factory()->for($form)->create(['payload' => ['values' => ['answer' => 'Historical answer']]]);
    $schemaBefore = $form->getRawOriginal('schema');
    $payloadBefore = $submission->getRawOriginal('payload');

    $editor = livewire(EditForm::class, ['record' => $form->getRouteKey()])
        ->call('save')
        ->assertHasFormErrors(['schema.0.key', 'schema.1.key'])
        ->assertSet('data.schema.0.key', 'answer')
        ->assertSet('data.schema.1.key', 'answer');

    expect($form->refresh()->getRawOriginal('schema'))->toBe($schemaBefore)
        ->and($submission->refresh()->getRawOriginal('payload'))->toBe($payloadBefore);

    $editor->fillForm(['schema' => [
        ['key' => 'answer', 'label' => 'First answer', 'type' => 'text'],
        ['key' => 'second_answer', 'label' => 'Second answer', 'type' => 'text'],
    ]])->call('save')->assertHasNoFormErrors();

    expect(ResolveVisibleFormFieldsAction::run($form->refresh())->pluck('key')->all())->toBe(['answer', 'second_answer'])
        ->and($submission->refresh()->getRawOriginal('payload'))->toBe($payloadBefore);
});

it('saves corrected distinct keys and validates and stores their answers independently', function (): void {
    Repeater::fake();
    $existing = Form::factory()->create();
    $creator = livewire(CreateForm::class)
        ->fillForm([
            'site_id' => $existing->site_id,
            'name' => 'Independent answers', 'handle' => 'independent-answers',
            'schema' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'validation_rules' => []],
                ['key' => 'email', 'label' => 'Quantity', 'type' => 'number', 'required' => true, 'validation_rules' => []],
            ],
            'settings' => ['store_submissions' => true],
        ])
        ->call('create')
        ->assertHasFormErrors(['schema.0.key', 'schema.1.key']);

    $creator->set('data.schema.1.key', 'quantity')
        ->call('create')
        ->assertHasNoFormErrors();

    $form = Form::query()->where('handle', 'independent-answers')->sole();
    $site = $form->site;
    throw_unless($site instanceof Site, RuntimeException::class);
    $origin = parse_url(url('/'));
    $site->siteDomains()->update([
        'domain' => $origin['host'] ?? 'localhost', 'scheme' => $origin['scheme'] ?? 'http',
        'path' => '/', 'port' => $origin['port'] ?? null,
    ]);
    app()->instance(FrontendContextReader::class, (new FrontendState)->withSite($site));
    Frontend::clearResolvedInstance(FrontendContextReader::class);
    auth()->logout();

    $publicForm = livewire(FormComponent::class, ['handle' => $form->handle])
        ->set('data.email', 'invalid-email')
        ->set('data.quantity', 'invalid-number')
        ->call('submit')
        ->assertHasErrors(['data.email' => 'email', 'data.quantity' => 'numeric']);

    expect($form->submissions()->count())->toBe(0);

    $publicForm->set('data.email', 'reader@example.test')
        ->call('submit')
        ->assertHasNoErrors(['data.email'])
        ->assertHasErrors(['data.quantity' => 'numeric'])
        ->set('data.quantity', 7)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $payload = $form->submissions()->sole()->payload;
    throw_unless($payload instanceof SubmissionPayloadData, RuntimeException::class);
    expect($payload->values)->toBe(['email' => 'reader@example.test', 'quantity' => 7]);
});
