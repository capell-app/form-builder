<?php

declare(strict_types=1);

use Capell\FormBuilder\Filament\Resources\Forms\FormResource;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\CreateForm;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\EditForm;
use Capell\FormBuilder\Filament\Resources\Forms\Pages\ListForms;
use Capell\FormBuilder\Models\Form;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Facades\Filament;

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
