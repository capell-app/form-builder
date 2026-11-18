<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\FormBuilder\Models\Submission;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<string, string> run(Submission $submission)
 */
final class ListSubmissionUploadsAction
{
    use AsFake;
    use AsObject;

    /**
     * Uploaded files on a submission as payload key => original file name, for the admin download picker.
     *
     * @return array<string, string>
     */
    public function handle(Submission $submission): array
    {
        $uploads = [];

        foreach ($submission->payload->values ?? [] as $key => $value) {
            if (is_array($value) && is_string($value['disk'] ?? null) && is_string($value['path'] ?? null)) {
                $uploads[(string) $key] = is_string($value['original_name'] ?? null) ? $value['original_name'] : (string) $key;
            }
        }

        return $uploads;
    }
}
