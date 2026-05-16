<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\BuildSubmissionPayloadEntriesAction;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Filament\Resources\Submissions\Pages\ListSubmissions;
use Capell\FormBuilder\Filament\Resources\Submissions\SubmissionResource;
use Capell\FormBuilder\Mail\SubmissionReplyMail;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can reply to a submission from the Filament table', function (): void {
    Mail::fake();
    test()->actingAsAdmin();

    $form = Form::factory()->create([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
            ],
        ],
    ]);

    $submission = Submission::factory()->for($form)->create([
        'payload' => [
            'values' => [
                'email' => 'customer@example.com',
            ],
        ],
    ]);

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$submission])
        ->assertActionVisible(TestAction::make('view_payload')->table($submission))
        ->callAction(
            TestAction::make('reply')->table($submission),
            data: [
                'subject' => 'Re: Capell enquiry',
                'message' => 'Thanks for getting in touch.',
            ],
        )
        ->assertHasNoActionErrors()
        ->assertNotified(__('capell-form-builder::message.reply_sent'));

    Mail::assertSent(
        SubmissionReplyMail::class,
        fn (SubmissionReplyMail $mail): bool => $mail->hasTo('customer@example.com')
            && $mail->subjectLine === 'Re: Capell enquiry'
            && $mail->messageBody === 'Thanks for getting in touch.',
    );
});

it('builds labelled submission payload entries for admin review', function (): void {
    $form = Form::factory()->create([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email address',
                'type' => 'email',
            ],
            [
                'key' => 'newsletter',
                'label' => 'Newsletter',
                'type' => 'checkbox',
            ],
        ],
    ]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => [
            'values' => [
                'email' => 'customer@example.com',
                'newsletter' => true,
            ],
        ],
    ]);

    expect(BuildSubmissionPayloadEntriesAction::run($submission)->all())->toBe([
        [
            'key' => 'email',
            'label' => 'Email address',
            'value' => 'customer@example.com',
        ],
        [
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'value' => __('capell-form-builder::generic.boolean.yes'),
        ],
    ]);
});

it('requires submission permission to access submissions', function (): void {
    test()->actingAsUser();

    expect(SubmissionResource::canAccess())->toBeFalse()
        ->and(SubmissionResource::canViewAny())->toBeFalse();
});

it('allows users with submission view permission to access submissions', function (): void {
    Permission::findOrCreate('ViewAny:Submission');
    test()->actingAs(test()->createUserWithPermission('ViewAny:Submission'));

    expect(SubmissionResource::canAccess())->toBeTrue()
        ->and(SubmissionResource::canViewAny())->toBeTrue();
});

it('hides the reply action from users without reply permission', function (): void {
    Permission::findOrCreate('ViewAny:Submission');
    $form = Form::factory()->create();
    $submission = Submission::factory()->for($form)->create();
    $user = test()->createUserWithPermission('ViewAny:Submission');
    assignFormBuilderSiteRole($user, (int) $form->site_id, ['ViewAny:Submission']);

    test()->actingAs($user);

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$submission])
        ->assertActionHidden(TestAction::make('reply')->table($submission));
});

it('allows users with reply permission to reply to a submission', function (): void {
    Mail::fake();
    Permission::findOrCreate('ViewAny:Submission');
    Permission::findOrCreate('Reply:Submission');
    $form = Form::factory()->create([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
            ],
        ],
    ]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => [
            'values' => [
                'email' => 'customer@example.com',
            ],
        ],
    ]);
    $user = test()->createUserWithPermission(['ViewAny:Submission', 'Reply:Submission']);
    assignFormBuilderSiteRole($user, (int) $form->site_id, ['ViewAny:Submission', 'Reply:Submission']);

    test()->actingAs($user);

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$submission])
        ->callAction(
            TestAction::make('reply')->table($submission),
            data: [
                'subject' => 'Re: Capell enquiry',
                'message' => 'Thanks for getting in touch.',
            ],
        )
        ->assertHasNoActionErrors();

    Mail::assertSent(
        SubmissionReplyMail::class,
        fn (SubmissionReplyMail $mail): bool => $mail->hasTo('customer@example.com'),
    );
});

it('does not expose reply for email-like values outside email fields', function (): void {
    Permission::findOrCreate('ViewAny:Submission');
    Permission::findOrCreate('Reply:Submission');
    $form = Form::factory()->create([
        'schema' => [
            [
                'key' => 'reference',
                'label' => 'Reference',
                'type' => 'text',
                'required' => false,
            ],
        ],
    ]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => [
            'values' => [
                'reference' => 'not-the-customer@example.com',
            ],
        ],
    ]);
    $user = test()->createUserWithPermission(['ViewAny:Submission', 'Reply:Submission']);
    assignFormBuilderSiteRole($user, (int) $form->site_id, ['ViewAny:Submission', 'Reply:Submission']);

    test()->actingAs($user);

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$submission])
        ->assertActionHidden(TestAction::make('reply')->table($submission));
});

it('allows users with update permission to triage submission status', function (): void {
    Permission::findOrCreate('ViewAny:Submission');
    Permission::findOrCreate('Update:Submission');

    $form = Form::factory()->create();
    $submission = Submission::factory()->for($form)->create([
        'status' => SubmissionStatus::New,
    ]);
    $user = test()->createUserWithPermission(['ViewAny:Submission', 'Update:Submission']);
    assignFormBuilderSiteRole($user, (int) $form->site_id, ['ViewAny:Submission', 'Update:Submission']);

    test()->actingAs($user);

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->callAction(TestAction::make('mark_read')->table($submission))
        ->assertHasNoActionErrors()
        ->assertNotified(__('capell-form-builder::message.status_updated'));

    expect($submission->refresh()->status)->toBe(SubmissionStatus::Read);

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->callAction(TestAction::make('archive')->table($submission))
        ->assertHasNoActionErrors();

    expect($submission->refresh()->status)->toBe(SubmissionStatus::Archived);

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->callAction(TestAction::make('mark_spam')->table($submission))
        ->assertHasNoActionErrors();

    expect($submission->refresh()->status)->toBe(SubmissionStatus::Spam);
});

it('denies reply to site-scoped users with update permission only', function (): void {
    Permission::findOrCreate('ViewAny:Submission');
    Permission::findOrCreate('Update:Submission');

    $form = Form::factory()->create([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
            ],
        ],
    ]);
    $submission = Submission::factory()->for($form)->create([
        'payload' => [
            'values' => [
                'email' => 'customer@example.com',
            ],
        ],
    ]);
    $user = test()->createUser();
    assignFormBuilderSiteRole($user, (int) $form->site_id, ['ViewAny:Submission', 'Update:Submission']);

    test()->actingAs($user);

    expect($user->can('view', $submission))->toBeTrue()
        ->and($user->can('update', $submission))->toBeTrue()
        ->and($user->can('reply', $submission))->toBeFalse();

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$submission])
        ->assertActionHidden(TestAction::make('reply')->table($submission));
});

it('allows role-only site-scoped users to reply and triage submissions', function (): void {
    Mail::fake();
    Permission::findOrCreate('ViewAny:Submission');
    Permission::findOrCreate('Reply:Submission');
    Permission::findOrCreate('Update:Submission');

    $form = Form::factory()->create([
        'schema' => [
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
            ],
        ],
    ]);
    $replySubmission = Submission::factory()->for($form)->create([
        'payload' => [
            'values' => [
                'email' => 'customer@example.com',
            ],
        ],
        'status' => SubmissionStatus::New,
    ]);
    $triageSubmission = Submission::factory()->for($form)->create([
        'payload' => [
            'values' => [
                'email' => 'triage@example.com',
            ],
        ],
        'status' => SubmissionStatus::New,
    ]);
    $user = test()->createUser();
    assignFormBuilderSiteRole($user, (int) $form->site_id, ['ViewAny:Submission', 'Reply:Submission', 'Update:Submission']);

    test()->actingAs($user);

    expect($user->can('view', $replySubmission))->toBeTrue()
        ->and($user->can('reply', $replySubmission))->toBeTrue()
        ->and($user->can('update', $triageSubmission))->toBeTrue();

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$replySubmission, $triageSubmission])
        ->callAction(
            TestAction::make('reply')->table($replySubmission),
            data: [
                'subject' => 'Re: Capell enquiry',
                'message' => 'Thanks for getting in touch.',
            ],
        )
        ->assertHasNoActionErrors();

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$replySubmission, $triageSubmission])
        ->callAction(TestAction::make('mark_read')->table($triageSubmission))
        ->assertHasNoActionErrors();

    Mail::assertSent(
        SubmissionReplyMail::class,
        fn (SubmissionReplyMail $mail): bool => $mail->hasTo('customer@example.com'),
    );

    expect($replySubmission->refresh()->status)->toBe(SubmissionStatus::Read)
        ->and($triageSubmission->refresh()->status)->toBe(SubmissionStatus::Read);
});

it('keeps site-scoped users inside their assigned submissions', function (): void {
    Permission::findOrCreate('ViewAny:Submission');
    Permission::findOrCreate('Reply:Submission');

    $assignedForm = Form::factory()->create(['name' => 'Assigned contact form']);
    $otherForm = Form::factory()->create(['name' => 'Other site contact form']);
    $assignedSubmission = Submission::factory()->for($assignedForm)->create();
    $otherSubmission = Submission::factory()->for($otherForm)->create();
    $user = test()->createUserWithPermission(['ViewAny:Submission', 'Reply:Submission']);
    assignFormBuilderSiteRole($user, (int) $assignedForm->site_id, ['ViewAny:Submission', 'Reply:Submission']);
    assignFormBuilderSiteRole($user, (int) $otherForm->site_id, [], 'unrelated-site-member');

    test()->actingAs($user);

    expect($user->can('view', $assignedSubmission))->toBeTrue()
        ->and($user->can('view', $otherSubmission))->toBeFalse()
        ->and($user->can('reply', $assignedSubmission))->toBeTrue()
        ->and($user->can('reply', $otherSubmission))->toBeFalse();

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertSee($assignedForm->name)
        ->assertDontSee($otherForm->name)
        ->assertCanSeeTableRecords([$assignedSubmission])
        ->assertCanNotSeeTableRecords([$otherSubmission]);
});

it('does not treat unrelated site membership as submission access', function (): void {
    Permission::findOrCreate('ViewAny:Submission');

    $form = Form::factory()->create(['name' => 'Restricted form']);
    $submission = Submission::factory()->for($form)->create();
    $user = test()->createUserWithPermission('ViewAny:Submission');
    assignFormBuilderSiteRole($user, (int) $form->site_id, [], 'unrelated-site-member');

    test()->actingAs($user);

    expect($user->can('view', $submission))->toBeFalse();

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertDontSee($form->name)
        ->assertCanNotSeeTableRecords([$submission]);
});

it('allows direct site-scoped submission permissions without a role assignment', function (): void {
    Permission::findOrCreate('ViewAny:Submission');

    $form = Form::factory()->create(['name' => 'Direct permission form']);
    $otherForm = Form::factory()->create(['name' => 'Other direct permission form']);
    $submission = Submission::factory()->for($form)->create();
    $otherSubmission = Submission::factory()->for($otherForm)->create();
    $user = test()->createUser();
    assignFormBuilderSitePermission($user, (int) $form->site_id, 'ViewAny:Submission');

    test()->actingAs($user);

    expect($user->can('view', $submission))->toBeTrue()
        ->and($user->can('view', $otherSubmission))->toBeFalse()
        ->and($user->can('reply', $submission))->toBeFalse();

    livewire(ListSubmissions::class)
        ->assertSuccessful()
        ->assertSee($form->name)
        ->assertDontSee($otherForm->name)
        ->assertCanSeeTableRecords([$submission])
        ->assertCanNotSeeTableRecords([$otherSubmission]);
});

/**
 * @param  list<string>  $permissions
 */
function assignFormBuilderSiteRole(User $user, int $siteId, array $permissions = [], string $roleName = 'form-builder-site-member'): void
{
    $role = Role::findOrCreate($roleName);

    if ($permissions !== []) {
        $role->givePermissionTo($permissions);
    }

    DB::table('model_has_roles')->insert([
        'role_id' => $role->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
        'team_id' => $siteId,
    ]);
}

function assignFormBuilderSitePermission(User $user, int $siteId, string $permissionName): void
{
    $permission = Permission::findOrCreate($permissionName);

    DB::table('model_has_permissions')->insert([
        'permission_id' => $permission->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
        'team_id' => $siteId,
    ]);
}
