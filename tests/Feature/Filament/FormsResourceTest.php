<?php

declare(strict_types=1);

use Capell\FormBuilder\Enums\FormFieldType;
use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\CreateForm;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\EditForm;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\ListForms;
use Capell\FormBuilder\Models\Form;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;

use function Pest\Livewire\livewire;

use Spatie\Permission\Models\Permission;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    test()->actingAsAdmin();
});

it('places forms under marketing studio navigation', function (): void {
    expect(FormResource::getNavigationGroup())->toBeNull()
        ->and(FormResource::getNavigationParentItem())->toBe((string) __('capell-admin::navigation.marketing_studio'))
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
