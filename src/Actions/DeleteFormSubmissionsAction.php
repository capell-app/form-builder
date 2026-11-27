<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Exceptions\FormHasSubmissionsUnderLegalHoldException;
use Capell\FormBuilder\Models\Form;
use Capell\FormBuilder\Models\Submission;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Clears a form's submissions before the form itself is deleted. Each one goes through DeleteSubmissionAction, so a
 * held submission is kept, and so is one whose uploads could not all be deleted. If any remain, the form is not
 * deleted and the operator is told how many were kept and why.
 *
 * @method static void run(Form $form)
 */
final class DeleteFormSubmissionsAction
{
    use AsFake;
    use AsObject;

    /** @throws FormHasSubmissionsUnderLegalHoldException when any submission had to be kept */
    public function handle(Form $form): void
    {
        Submission::query()->where('form_id', $form->getKey())->select('id')->chunkById(200, static function ($chunk): void {
            foreach ($chunk->modelKeys() as $id) {
                if (is_numeric($id)) {
                    DeleteSubmissionAction::run((int) $id);
                }
            }
        });

        $remaining = Submission::query()->where('form_id', $form->getKey())->count();

        if ($remaining === 0) {
            return;
        }

        $held = Submission::query()->where('form_id', $form->getKey())->where('legal_hold', true)->count();

        throw FormHasSubmissionsUnderLegalHoldException::make($held, $remaining - $held);
    }
}
