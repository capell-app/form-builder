<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\FormBuilder\Actions\FindActiveSiteFormAction;
use Capell\FormBuilder\Models\Form;

function foundSiteFormId(Site $site, int|string $handle): mixed
{
    $form = FindActiveSiteFormAction::run($site->id, $handle);

    return $form instanceof Form ? $form->getKey() : null;
}

it('prefers a form whose handle matches over a same-site form whose id matches', function (): void {
    $site = Site::factory()->createOne();
    $byId = Form::factory()->create(['site_id' => $site->getKey(), 'handle' => 'contact', 'is_active' => true]);
    $byHandle = Form::factory()->create(['site_id' => $site->getKey(), 'handle' => (string) $byId->getKey(), 'is_active' => true]);

    expect(foundSiteFormId($site, (string) $byId->getKey()))->toBe($byHandle->getKey())
        ->and(foundSiteFormId($site, (int) $byId->getKey()))->toBe($byHandle->getKey());

    $byHandle->update(['is_active' => false]);

    expect(foundSiteFormId($site, (string) $byId->getKey()))->toBe($byId->getKey());
});

it('never finds an inactive form, another site\'s form or a blank handle', function (): void {
    $site = Site::factory()->createOne();
    $other = Site::factory()->createOne();
    Form::factory()->create(['site_id' => $site->getKey(), 'handle' => 'quote', 'is_active' => false]);
    Form::factory()->create(['site_id' => $other->getKey(), 'handle' => 'contact', 'is_active' => true]);

    expect(FindActiveSiteFormAction::run($site->id, 'quote'))->toBeNull()
        ->and(FindActiveSiteFormAction::run($site->id, 'contact'))->toBeNull()
        ->and(FindActiveSiteFormAction::run($site->id, '  '))->toBeNull()
        ->and(FindActiveSiteFormAction::run($site->id, null))->toBeNull();
});
