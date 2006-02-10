<?php

declare(strict_types=1);

use Capell\FormBuilder\Actions\CreateSubmissionAction;
use Capell\FormBuilder\Actions\DispatchUnstoredFormSubmissionAction;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Events\FormSubmitted;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

it('uses the editor label without changing direct submission error keys', function (bool $stored): void {
    app()->setLocale('en');
    $form = Form::factory()->create([
        'schema' => [
            ['key' => 'company_name', 'label' => 'Company name', 'type' => 'text', 'required' => true],
        ],
    ]);
    Event::fake([FormSubmitted::class]);
    $before = Submission::query()->count();
    $meta = new SubmissionMetaData(ipAddress: '127.0.0.1', userAgent: 'Pest');

    try {
        if ($stored) {
            CreateSubmissionAction::run($form, [], $meta);
        } else {
            DispatchUnstoredFormSubmissionAction::run($form, [], $meta);
        }

        $this->fail('Invalid input must raise a validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'company_name' => ['The Company name field is required.'],
        ]);
    }

    expect(Submission::query()->count())->toBe($before);
    Event::assertNotDispatched(FormSubmitted::class);
})->with(['stored' => true, 'unstored' => false]);
