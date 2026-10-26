<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<string, true>|null */
    private ?array $permissionCache = null;

    private ?bool $superAdminCache = null;

    /** @var list<int>|null */
    private ?array $projectIdCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'user_profile');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'user_project_access')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->superAdminCache ??= $this->roles()->where('roles.slug', Role::SUPER_ADMIN)->exists();
    }

    /**
     * Permisos efectivos = unión de los permisos de sus roles y de sus perfiles.
     *
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        return array_keys($this->loadPermissionMap());
    }

    public function hasPermission(string $slug): bool
    {
        return $this->isSuperAdmin() || isset($this->loadPermissionMap()[$slug]);
    }

    public function canAccessProject(Project|int $project): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $id = $project instanceof Project ? $project->id : $project;

        return in_array($id, $this->accessibleProjectIds(), true);
    }

    /** @return list<int> */
    public function accessibleProjectIds(): array
    {
        return $this->projectIdCache ??= $this->projects()->pluck('projects.id')->map(fn ($id) => (int) $id)->all();
    }

    /** Descarta cachés de permisos tras cambiar roles/perfiles. */
    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->superAdminCache = null;
        $this->projectIdCache = null;
    }

    /** @return array<string, true> */
    private function loadPermissionMap(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        $slugs = $this->isSuperAdmin()
            ? Permission::query()->pluck('slug')
            : Permission::query()
                ->where(function ($q) {
                    $q->whereIn('permissions.id', fn ($s) => $s->select('permission_id')->from('role_permission')
                        ->join('user_role', 'user_role.role_id', '=', 'role_permission.role_id')
                        ->where('user_role.user_id', $this->getKey()))
                        ->orWhereIn('permissions.id', fn ($s) => $s->select('permission_id')->from('profile_permission')
                            ->join('user_profile', 'user_profile.profile_id', '=', 'profile_permission.profile_id')
                            ->where('user_profile.user_id', $this->getKey()));
                })
                ->pluck('slug');

        return $this->permissionCache = array_fill_keys($slugs->all(), true);
    }
}
