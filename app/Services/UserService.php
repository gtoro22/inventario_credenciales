<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(private AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $roleIds = $data['role_ids'] ?? [];
            $profileIds = $data['profile_ids'] ?? [];
            $projectIds = $data['project_ids'] ?? [];

            $this->assertCanGrant($actor, $roleIds, $profileIds);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->roles()->sync($roleIds);
            $user->profiles()->sync($profileIds);
            $this->syncProjects($actor, $user, $projectIds);

            $this->audit->log('user.created', $user, [], $this->snapshot($user->fresh()), user: $actor, label: $user->email);

            return $user->fresh(['roles', 'profiles', 'projects']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data) {
            $before = $this->snapshot($user);

            $roleIds = $data['role_ids'] ?? $user->roles()->pluck('roles.id')->all();
            $profileIds = $data['profile_ids'] ?? $user->profiles()->pluck('profiles.id')->all();

            // Solo se validan como "concesión" los roles/perfiles nuevos.
            $newRoles = array_diff($roleIds, $user->roles()->pluck('roles.id')->all());
            $newProfiles = array_diff($profileIds, $user->profiles()->pluck('profiles.id')->all());
            $this->assertCanGrant($actor, $newRoles, $newProfiles);

            $isActive = $data['is_active'] ?? $user->is_active;

            $this->assertSafeChange($actor, $user, $roleIds, (bool) $isActive);

            $user->fill(collect($data)->only(['name', 'email', 'is_active'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();

            $user->roles()->sync($roleIds);
            $user->profiles()->sync($profileIds);
            if (array_key_exists('project_ids', $data)) {
                $this->syncProjects($actor, $user, $data['project_ids']);
            }
            $user->flushPermissionCache();

            $after = $this->snapshot($user->fresh());
            if (! empty($data['password'])) {
                $after['password'] = AuditService::REDACTED.' (modificado)';
            }

            [$old, $new] = $this->audit->diff($before, $after);
            if ($old !== [] || $new !== []) {
                $this->audit->log('user.updated', $user, $old, $new, user: $actor, label: $user->email);
            }

            return $user->fresh(['roles', 'profiles', 'projects']);
        });
    }

    /**
     * Un actor sin acceso global solo gestiona los proyectos que él mismo ve:
     * los demás accesos del usuario destino se conservan intactos.
     *
     * @param  list<int>  $requested
     */
    private function syncProjects(User $actor, User $user, array $requested): void
    {
        if ($actor->isSuperAdmin()) {
            $user->projects()->sync($requested);

            return;
        }

        $manageable = $actor->accessibleProjectIds();
        $kept = array_diff($user->projects()->pluck('projects.id')->all(), $manageable);

        $user->projects()->sync(array_values(array_unique([...$kept, ...array_intersect($requested, $manageable)])));
    }

    public function delete(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages(['user' => 'No puedes eliminar tu propia cuenta.']);
        }

        $this->assertNotLastSuperAdmin($user, 'eliminar');

        DB::transaction(function () use ($actor, $user) {
            $this->audit->log('user.deleted', $user, $this->snapshot($user), user: $actor, label: $user->email);
            $user->delete();
        });
    }

    /**
     * Un actor que no es superadmin solo puede conceder roles/perfiles cuyos
     * permisos ya posee (evita escalada de privilegios), y nunca super-admin.
     *
     * @param  iterable<int>  $roleIds
     * @param  iterable<int>  $profileIds
     */
    public function assertCanGrant(User $actor, iterable $roleIds, iterable $profileIds): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $roles = Role::with('permissions')->whereIn('id', collect($roleIds)->all())->get();
        $profiles = Profile::with('permissions')->whereIn('id', collect($profileIds)->all())->get();

        if ($roles->contains->isSuperAdmin()) {
            throw ValidationException::withMessages(['role_ids' => 'Solo un superadministrador puede asignar el rol Super Administrador.']);
        }

        $mine = $actor->permissionSlugs();
        $requested = $roles->pluck('permissions')->merge($profiles->pluck('permissions'))->flatten()->pluck('slug')->unique();

        if ($requested->diff($mine)->isNotEmpty()) {
            throw ValidationException::withMessages(['role_ids' => 'No puedes conceder permisos que tú mismo no posees.']);
        }
    }

    /** @param list<int> $roleIds */
    private function assertSafeChange(User $actor, User $user, array $roleIds, bool $isActive): void
    {
        if ($actor->is($user) && ! $isActive) {
            throw ValidationException::withMessages(['is_active' => 'No puedes desactivar tu propia cuenta.']);
        }

        $superId = Role::where('slug', Role::SUPER_ADMIN)->value('id');
        $losesSuper = $user->isSuperAdmin() && ! in_array($superId, $roleIds);

        if ($losesSuper && $actor->is($user)) {
            throw ValidationException::withMessages(['role_ids' => 'No puedes quitarte el rol Super Administrador a ti mismo.']);
        }

        if ($losesSuper || ($user->isSuperAdmin() && ! $isActive)) {
            $this->assertNotLastSuperAdmin($user, 'degradar o desactivar');
        }
    }

    private function assertNotLastSuperAdmin(User $user, string $verb): void
    {
        if (! $user->isSuperAdmin()) {
            return;
        }

        $others = User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->whereHas('roles', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages(['user' => "No se puede {$verb} al último superadministrador activo."]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->roles()->orderBy('slug')->pluck('slug')->all(),
            'profiles' => $user->profiles()->orderBy('slug')->pluck('slug')->all(),
            'projects' => $user->projects()->orderBy('projects.code')->pluck('projects.code')->all(),
        ];
    }
}
