<?php

namespace App\Models;

use App\Casts\EncryptedSecret;
use App\Enums\CredentialStatus;
use App\Enums\Criticality;
use App\Enums\Environment;
use Database\Factories\CredentialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Credential extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'credential_type_id', 'criticality_level_id', 'name', 'username', 'secret',
        'host', 'port', 'environment', 'notes', 'expires_at', 'status', 'created_by', 'updated_by',
    ];

    /** El secreto jamás se serializa; solo se lee explícitamente en el reveal. */
    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => EncryptedSecret::class,
            'environment' => Environment::class,
            'status' => CredentialStatus::class,
            'expires_at' => 'date',
            'port' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CredentialType::class, 'credential_type_id');
    }

    public function criticality(): BelongsTo
    {
        return $this->belongsTo(CriticalityLevel::class, 'criticality_level_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasSecret(): bool
    {
        return ! empty($this->getRawOriginal('secret'));
    }

    public function isCritical(): bool
    {
        return $this->criticality?->slug === Criticality::Critical->value;
    }

    /**
     * Credenciales que el usuario puede ver: solo de sus proyectos y, si no
     * tiene `credentials.critical`, sin las de criticidad crítica.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isSuperAdmin()) {
            $query->whereIn('credentials.project_id', $user->projects()->select('projects.id'));

            if (! $user->hasPermission('credentials.critical')) {
                $query->whereHas('criticality', fn (Builder $q) => $q->where('slug', '!=', Criticality::Critical->value));
            }
        }

        return $query;
    }
}
