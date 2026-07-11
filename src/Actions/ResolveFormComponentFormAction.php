<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Actions;

use Capell\Core\Models\Site;
use Capell\FormBuilder\Models\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * @method static Form|null run(int|string|null $handle = null, string $formReference = '', ?Site $site = null)
 */
final class ResolveFormComponentFormAction
{
    use AsObject;

    public static function referenceFor(Form $form): string
    {
        return Crypt::encryptString(json_encode([
            'form_id' => $form->getKey(),
            'site_id' => $form->site_id,
        ], JSON_THROW_ON_ERROR));
    }

    public function handle(int|string|null $handle = null, string $formReference = '', ?Site $site = null): ?Form
    {
        return $this->resolveFormFromReference($formReference, $site)
            ?? $this->resolveFormForSite($handle, $site);
    }

    private function resolveFormForSite(int|string|null $handle, ?Site $site): ?Form
    {
        if ($handle === null || $handle === '' || ! $site instanceof Site) {
            return null;
        }

        return Form::query()
            ->active()
            ->where('site_id', $site->getKey())
            ->where(function (Builder $builder) use ($handle): void {
                if (is_numeric($handle)) {
                    $builder->whereKey((int) $handle);
                }

                $builder->orWhere('handle', (string) $handle);
            })
            ->first();
    }

    private function resolveFormFromReference(string $formReference, ?Site $site): ?Form
    {
        if ($formReference === '') {
            return null;
        }

        try {
            $reference = json_decode(Crypt::decryptString($formReference), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($reference)) {
            return null;
        }

        $formId = $reference['form_id'] ?? null;
        $siteId = $reference['site_id'] ?? null;

        if (! is_numeric($formId) || ! is_numeric($siteId)) {
            return null;
        }

        if ($site instanceof Site && (int) $site->getKey() !== (int) $siteId) {
            return null;
        }

        return Form::query()
            ->active()
            ->whereKey((int) $formId)
            ->where('site_id', (int) $siteId)
            ->first();
    }
}
