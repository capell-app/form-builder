<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Policies;

use Capell\Admin\Policies\Concerns\ResolvesShieldPermission;
use Capell\Admin\Support\SiteScope;
use Capell\FormBuilder\Models\Form;
use Illuminate\Foundation\Auth\User;
use Throwable;

final class FormPolicy
{
    use ResolvesShieldPermission;

    private const string SUBJECT = 'Form';

    public function viewAny(User $user): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view']);
    }

    public function view(User $user, Form $form): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view'])
            && $this->canUseFormSite($user, $form);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'create');
    }

    public function update(User $user, Form $form): bool
    {
        return $this->hasPermission($user, 'update')
            && $this->canUseFormSite($user, $form);
    }

    public function delete(User $user, Form $form): bool
    {
        return $this->hasPermission($user, 'delete')
            && $this->canUseFormSite($user, $form);
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'delete_any');
    }

    public function restore(User $user, Form $form): bool
    {
        return $this->hasPermission($user, 'restore')
            && $this->canUseFormSite($user, $form);
    }

    public function restoreAny(User $user): bool
    {
        return $this->hasPermission($user, 'restore_any');
    }

    public function forceDelete(User $user, Form $form): bool
    {
        return $this->hasPermission($user, 'force_delete')
            && $this->canUseFormSite($user, $form);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'force_delete_any');
    }

    /**
     * @param  list<string>  $abilities
     */
    private function hasAnyPermission(User $user, array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($this->hasPermission($user, $ability)) {
                return true;
            }
        }

        return false;
    }

    private function hasPermission(User $user, string $ability): bool
    {
        if (SiteScope::isGlobalActor($user)) {
            return true;
        }

        try {
            return $user->checkPermissionTo(self::permission($ability, self::SUBJECT));
        } catch (Throwable) {
            return false;
        }
    }

    private function canUseFormSite(User $user, Form $form): bool
    {
        if ($form->site_id === null || SiteScope::isGlobalActor($user)) {
            return true;
        }

        return $user->getAssignedSiteIds()->contains($form->site_id);
    }
}
