<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Policies;

use Capell\Admin\Policies\Concerns\ResolvesShieldPermission;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Capell\FormBuilder\Models\Submission;
use Capell\FormBuilder\Support\SubmissionSiteAccess;
use Illuminate\Foundation\Auth\User;
use Throwable;

final class SubmissionPolicy
{
    use ResolvesShieldPermission;

    private const string SUBJECT = 'Submission';

    public function viewAny(User $user): bool
    {
        if ($this->hasPermission($user, 'view_any')) {
            return true;
        }

        if ($this->hasPermission($user, 'view')) {
            return true;
        }

        return SubmissionSiteAccess::actorCanAccessAnySite($user, SubmissionSiteAccess::VIEW_ABILITIES);
    }

    public function view(User $user, Submission $submission): bool
    {
        return $this->viewAny($user)
            && SubmissionSiteAccess::actorCanAccessSite(
                $user,
                $submission->site_id,
                SubmissionSiteAccess::VIEW_ABILITIES,
            );
    }

    public function reply(User $user, Submission $submission): bool
    {
        if ($submission->status === SubmissionStatus::Spam) {
            return false;
        }

        return SubmissionSiteAccess::actorCanAccessSite(
            $user,
            $submission->site_id,
            SubmissionSiteAccess::REPLY_ABILITIES,
        );
    }

    public function update(User $user, Submission $submission): bool
    {
        return SubmissionSiteAccess::actorCanAccessSite(
            $user,
            $submission->site_id,
            SubmissionSiteAccess::UPDATE_ABILITIES,
        );
    }

    private function hasPermission(User $user, string $ability): bool
    {
        if ($user->hasRole(config('capell.roles.super_admin', 'super_admin'))) {
            return true;
        }

        try {
            return $user->checkPermissionTo(self::permission($ability, self::SUBJECT));
        } catch (Throwable) {
            return false;
        }
    }
}
