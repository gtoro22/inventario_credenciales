<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'is_active', 'owner_id', 'tags'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'tags' => 'array'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_project_access')->withTimestamps();
    }

    /**
     * Restringe a los proyectos que el usuario puede ver. Un usuario sin
     * acceso no debe poder siquiera comprobar que el proyecto existe.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('projects.id', $user->projects()->select('projects.id'));
    }
}
