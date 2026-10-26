<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** CRUD compartido de roles y perfiles (ambos agrupan permisos). */
class RoleService
{
    public function __construct(private AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, string $modelClass, array $data): Role|Profile
    {
        $this->assertCanGrantPermissions($actor, $data['permission_ids'] ?? []);

        return DB::transaction(function () use ($actor, $modelClass, $data) {
            /** @var Role|Profile $model */
            $model = $modelClass::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($modelClass, $data['name']),
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);
            $model->permissions()->sync($data['permission_ids'] ?? []);

            $this->audit->log($this->action($model, 'created'), $model, [], $this->snapshot($model), user: $actor);

            return $model;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Role|Profile $model, array $data): Role|Profile
    {
        if ($model instanceof Role && $model->isSuperAdmin()) {
            throw ValidationException::withMessages(['role' => 'El rol Super Administrador no se puede modificar: posee todos los permisos de forma implícita.']);
        }

        if (isset($data['permission_ids'])) {
            $current = $model->permissions()->pluck('permissions.id')->all();
            $this->assertCanGrantPermissions($actor, array_diff($data['permission_ids'], $current));
        }

        return DB::transaction(function () use ($actor, $model, $data) {
            $before = $this->snapshot($model);

            $model->fill(collect($data)->only(['name', 'description'])->all())->save();
            if (isset($data['permission_ids'])) {
                $model->permissions()->sync($data['permission_ids']);
            }

            [$old, $new] = $this->audit->diff($before, $this->snapshot($model->fresh()));
            if ($old !== [] || $new !== []) {
                $this->audit->log($this->action($model, 'updated'), $model, $old, $new, user: $actor);
            }

            return $model->fresh('permissions');
        });
    }

    public function delete(User $actor, Role|Profile $model): void
    {
        if ($model->is_system) {
            throw ValidationException::withMessages(['role' => 'Los elementos del sistema no se pueden eliminar.']);
        }

        if ($model->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'Hay usuarios asignados; reasígnalos antes de eliminar.']);
        }

        DB::transaction(function () use ($actor, $model) {
            $this->audit->log($this->action($model, 'deleted'), $model, $this->snapshot($model), user: $actor);
            $model->delete();
        });
    }

    /** @param iterable<int> $permissionIds */
    private function assertCanGrantPermissions(User $actor, iterable $permissionIds): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $requested = \App\Models\Permission::whereIn('id', collect($permissionIds)->all())->pluck('slug');

        if ($requested->diff($actor->permissionSlugs())->isNotEmpty()) {
            throw ValidationException::withMessages(['permission_ids' => 'No puedes conceder permisos que tú mismo no posees.']);
        }
    }

    private function uniqueSlug(string $modelClass, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;

        while ($modelClass::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function action(Model $model, string $verb): string
    {
        return ($model instanceof Role ? 'role.' : 'profile.').$verb;
    }

    /** @return array<string, mixed> */
    private function snapshot(Role|Profile $model): array
    {
        return [
            'name' => $model->name,
            'description' => $model->description,
            'permissions' => $model->permissions()->orderBy('slug')->pluck('slug')->all(),
        ];
    }
}
