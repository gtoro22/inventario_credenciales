<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Credential;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Punto único de escritura de auditoría. Todo valor cuya clave parezca
 * sensible se sustituye por un marcador antes de persistirse.
 */
class AuditService
{
    public const REDACTED = '[REDACTED]';

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(
        string $action,
        ?Model $entity = null,
        array $old = [],
        array $new = [],
        ?int $projectId = null,
        ?User $user = null,
        ?string $email = null,
        ?string $label = null,
    ): AuditLog {
        $user ??= Auth::user();

        if ($projectId === null && $entity !== null) {
            $projectId = match (true) {
                $entity instanceof Project => $entity->getKey(),
                $entity instanceof Credential => $entity->project_id,
                default => null,
            };
        }

        $request = app()->bound('request') ? request() : null;

        return AuditLog::create([
            'user_id' => $user?->getKey(),
            'user_email' => $user?->email ?? $email,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'entity_label' => $label ?? ($entity?->name ?? null),
            'project_id' => $projectId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            'old_values' => $old === [] ? null : $this->sanitize($old),
            'new_values' => $new === [] ? null : $this->sanitize($new),
        ]);
    }

    /**
     * Devuelve [old, new] solo con las claves que cambiaron.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;

            if ($b !== $a) {
                $old[$key] = $b;
                $new[$key] = $a;
            }
        }

        return [$old, $new];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitize(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key) && $value !== null) {
                $out[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $out[$key] = $this->sanitize($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (config('vault.audit_redacted_keys', []) as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
