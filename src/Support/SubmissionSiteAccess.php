<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Support;

use Capell\Admin\Policies\Concerns\ResolvesShieldPermission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SubmissionSiteAccess
{
    use ResolvesShieldPermission;

    /** @var list<string> */
    public const array VIEW_ABILITIES = ['view_any', 'view'];

    /** @var list<string> */
    public const array REPLY_ABILITIES = ['reply'];

    /** @var list<string> */
    public const array UPDATE_ABILITIES = ['update'];

    private const string SUBJECT = 'Submission';

    /**
     * @param  list<string>  $abilities
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyToQuery(
        Builder $query,
        ?Authenticatable $actor = null,
        array $abilities = self::VIEW_ABILITIES,
    ): Builder {
        return self::applyToSiteScopedQuery($query, $actor, 'site_id', $abilities);
    }

    /**
     * @param  list<string>  $abilities
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyToSiteScopedQuery(
        Builder $query,
        ?Authenticatable $actor = null,
        string $siteColumn = 'site_id',
        array $abilities = self::VIEW_ABILITIES,
    ): Builder {
        $actor ??= auth()->user();

        if (! $actor instanceof Authenticatable) {
            return $query->whereRaw('1 = 0');
        }

        if (self::isGlobalActor($actor)) {
            return $query;
        }

        $siteIds = self::permittedSiteIds($actor, $abilities);

        return $siteIds->isNotEmpty()
            ? $query->whereIn(self::qualifyColumn($query, $siteColumn), $siteIds)
            : $query->whereRaw('1 = 0');
    }

    /**
     * @param  list<string>  $abilities
     */
    public static function actorCanAccessSite(
        ?Authenticatable $actor,
        ?int $siteId,
        array $abilities = self::VIEW_ABILITIES,
    ): bool {
        if (! $actor instanceof Authenticatable || $siteId === null) {
            return false;
        }

        if (self::isGlobalActor($actor)) {
            return true;
        }

        return self::permittedSiteIds($actor, $abilities)->contains($siteId);
    }

    /**
     * @param  list<string>  $abilities
     */
    public static function actorCanAccessAnySite(?Authenticatable $actor, array $abilities = self::VIEW_ABILITIES): bool
    {
        if (! $actor instanceof Authenticatable) {
            return false;
        }

        if (self::isGlobalActor($actor)) {
            return true;
        }

        return self::permittedSiteIds($actor, $abilities)->isNotEmpty();
    }

    private static function isGlobalActor(Authenticatable $actor): bool
    {
        $configuredRole = config('capell.roles.super_admin', config('filament-shield.super_admin.name', 'super_admin'));
        $superAdminRole = is_string($configuredRole) && $configuredRole !== '' ? $configuredRole : 'super_admin';

        return $actor->hasRole($superAdminRole);
    }

    /**
     * @param  list<string>  $abilities
     * @return Collection<int, positive-int>
     */
    private static function permittedSiteIds(Authenticatable $actor, array $abilities): Collection
    {
        $permissionNames = collect($abilities)
            ->map(fn (string $ability): string => self::permission($ability, self::SUBJECT))
            ->unique()
            ->values();

        if ($permissionNames->isEmpty()) {
            return collect();
        }

        return self::rolePermissionSiteIds($actor, $permissionNames)
            ->merge(self::directPermissionSiteIds($actor, $permissionNames))
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, string>  $permissionNames
     * @return Collection<int, positive-int>
     */
    private static function rolePermissionSiteIds(Authenticatable $actor, Collection $permissionNames): Collection
    {
        $tables = self::permissionTableNames();
        $teamColumn = self::teamColumn();

        if (! Schema::hasTable($tables['model_has_roles'])
            || ! Schema::hasTable($tables['role_has_permissions'])
            || ! Schema::hasTable($tables['permissions'])
            || ! Schema::hasColumn($tables['model_has_roles'], $teamColumn)) {
            return collect();
        }

        return DB::table($tables['model_has_roles'])
            ->join($tables['role_has_permissions'], $tables['role_has_permissions'] . '.role_id', '=', $tables['model_has_roles'] . '.role_id')
            ->join($tables['permissions'], $tables['permissions'] . '.id', '=', $tables['role_has_permissions'] . '.permission_id')
            ->where($tables['model_has_roles'] . '.model_type', self::modelType($actor))
            ->where($tables['model_has_roles'] . '.model_id', self::modelId($actor))
            ->whereNotNull($tables['model_has_roles'] . '.' . $teamColumn)
            ->whereIn($tables['permissions'] . '.name', $permissionNames->all())
            ->pluck($tables['model_has_roles'] . '.' . $teamColumn)
            ->map(fn (mixed $siteId): int => (int) $siteId)
            ->filter(fn (int $siteId): bool => $siteId > 0)
            ->values()
            ->map(fn (int $siteId): int => $siteId);
    }

    /**
     * @param  Collection<int, string>  $permissionNames
     * @return Collection<int, positive-int>
     */
    private static function directPermissionSiteIds(Authenticatable $actor, Collection $permissionNames): Collection
    {
        $tables = self::permissionTableNames();
        $teamColumn = self::teamColumn();

        if (! Schema::hasTable($tables['model_has_permissions'])
            || ! Schema::hasTable($tables['permissions'])
            || ! Schema::hasColumn($tables['model_has_permissions'], $teamColumn)) {
            return collect();
        }

        return DB::table($tables['model_has_permissions'])
            ->join($tables['permissions'], $tables['permissions'] . '.id', '=', $tables['model_has_permissions'] . '.permission_id')
            ->where($tables['model_has_permissions'] . '.model_type', self::modelType($actor))
            ->where($tables['model_has_permissions'] . '.model_id', self::modelId($actor))
            ->whereNotNull($tables['model_has_permissions'] . '.' . $teamColumn)
            ->whereIn($tables['permissions'] . '.name', $permissionNames->all())
            ->pluck($tables['model_has_permissions'] . '.' . $teamColumn)
            ->map(fn (mixed $siteId): int => (int) $siteId)
            ->filter(fn (int $siteId): bool => $siteId > 0)
            ->values()
            ->map(fn (int $siteId): int => $siteId);
    }

    /**
     * @return array{model_has_permissions: string, model_has_roles: string, permissions: string, role_has_permissions: string}
     */
    private static function permissionTableNames(): array
    {
        $configuredTableNames = config('permission.table_names', []);

        return [
            'model_has_permissions' => is_array($configuredTableNames) && is_string($configuredTableNames['model_has_permissions'] ?? null)
                ? $configuredTableNames['model_has_permissions']
                : 'model_has_permissions',
            'model_has_roles' => is_array($configuredTableNames) && is_string($configuredTableNames['model_has_roles'] ?? null)
                ? $configuredTableNames['model_has_roles']
                : 'model_has_roles',
            'permissions' => is_array($configuredTableNames) && is_string($configuredTableNames['permissions'] ?? null)
                ? $configuredTableNames['permissions']
                : 'permissions',
            'role_has_permissions' => is_array($configuredTableNames) && is_string($configuredTableNames['role_has_permissions'] ?? null)
                ? $configuredTableNames['role_has_permissions']
                : 'role_has_permissions',
        ];
    }

    private static function teamColumn(): string
    {
        $teamColumnConfig = config('permission.column_names.team_foreign_key', 'team_id');

        return is_string($teamColumnConfig) && $teamColumnConfig !== '' ? $teamColumnConfig : 'team_id';
    }

    private static function modelType(Authenticatable $actor): string
    {
        return $actor->getMorphClass();
    }

    private static function modelId(Authenticatable $actor): mixed
    {
        return $actor->getKey();
    }

    /**
     * @param  Builder<Model>  $query
     */
    private static function qualifyColumn(Builder $query, string $column): string
    {
        return str_contains($column, '.')
            ? $column
            : $query->getModel()->qualifyColumn($column);
    }
}
