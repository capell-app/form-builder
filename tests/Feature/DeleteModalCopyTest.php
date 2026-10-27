<?php

declare(strict_types=1);

use Capell\FormBuilder\Filament\Resources\Forms\Pages\EditForm;
use Capell\FormBuilder\Models\Form;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\Livewire;

uses(CreatesAdminUser::class);

/**
 * @param  class-string<Component>  $page
 * @param  Closure(): Model  $makeRecord
 */
it('renders translated delete consequences in the real admin surface', function (string $page, Closure $makeRecord, string $mode, string $actionName, string $translation, bool $force): void {
    test()->actingAsAdmin();
    $heading = __($translation . '.heading');
    $description = __($translation . '.description');

    expect($heading)->toBeString()->not->toBeEmpty()->not->toBe($translation . '.heading')
        ->and($description)->toBeString()->not->toBeEmpty()->not->toBe($translation . '.description');

    $record = $makeRecord();
    if ($force) {
        $record->delete();
    }

    $parameters = $mode === 'page' ? ['record' => $record->getRouteKey()] : [];
    $component = Livewire::test($page, $parameters)->assertSuccessful();
    if ($mode === 'page') {
        $component->mountAction($actionName);
    } else {
        if ($force) {
            $component->filterTable('trashed', true);
        }

        if ($mode === 'bulk') {
            $component->mountTableBulkAction($actionName, [$record->getKey()]);
        } else {
            $component->mountTableAction($actionName, $record);
        }
    }

    $component->assertMountedActionModalSee($heading)->assertMountedActionModalSee($description);
})->with([
    'EditForm DeleteAction' => [EditForm::class, fn (): Model => Form::factory()->create(), 'page', 'delete', 'capell-form-builder::form.delete_modal', false],
]);
