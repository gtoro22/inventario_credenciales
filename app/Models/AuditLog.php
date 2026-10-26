<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inmutable: no se permite actualizar ni borrar entradas.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los registros de auditoría son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los registros de auditoría son inmutables.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
