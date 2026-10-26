<?php

namespace App\Services;

use App\Models\Credential;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CredentialService
{
    public function __construct(private AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Credential
    {
        return DB::transaction(function () use ($actor, $data) {
            $credential = Credential::create([
                ...$data,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $credential->load(['type', 'criticality']);
            $snapshot = $this->snapshot($credential);

            $this->audit->log('credential.created', $credential, [], $snapshot, user: $actor);

            return $credential;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Credential $credential, array $data): Credential
    {
        return DB::transaction(function () use ($actor, $credential, $data) {
            $credential->loadMissing(['type', 'criticality']);
            $before = $this->snapshot($credential);

            $secretChanged = array_key_exists('secret', $data) && $data['secret'] !== null && $data['secret'] !== '';

            if (! $secretChanged) {
                unset($data['secret']);
            }

            $credential->fill([...$data, 'updated_by' => $actor->id])->save();
            $credential->load(['type', 'criticality']);

            $after = $this->snapshot($credential);
            if ($secretChanged) {
                $after['secret'] = AuditService::REDACTED.' (modificado)';
            }

            [$old, $new] = $this->audit->diff($before, $after);

            if ($old !== [] || $new !== []) {
                $this->audit->log('credential.updated', $credential, $old, $new, user: $actor);
            }

            return $credential;
        });
    }

    public function delete(User $actor, Credential $credential): void
    {
        DB::transaction(function () use ($actor, $credential) {
            $credential->loadMissing(['type', 'criticality']);
            $this->audit->log('credential.deleted', $credential, $this->snapshot($credential), user: $actor);
            $credential->delete();
        });
    }

    /**
     * Devuelve el secreto en claro y deja constancia en auditoría.
     *
     * @param  'view'|'copy'  $purpose
     */
    public function reveal(User $actor, Credential $credential, string $purpose): ?string
    {
        $plain = $credential->secret;

        $this->audit->log(
            $purpose === 'copy' ? 'credential.secret_copied' : 'credential.secret_revealed',
            $credential,
            user: $actor,
        );

        return $plain;
    }

    /** @return array<string, mixed> */
    public function snapshot(Credential $credential): array
    {
        return [
            'name' => $credential->name,
            'project_id' => $credential->project_id,
            'type' => $credential->type?->slug,
            'criticality' => $credential->criticality?->slug,
            'environment' => $credential->environment?->value,
            'status' => $credential->status?->value,
            'username' => $credential->username,
            'host' => $credential->host,
            'port' => $credential->port,
            'expires_at' => $credential->expires_at?->toDateString(),
            // Solo huella: así se detecta el cambio sin exponer notas potencialmente sensibles.
            'notes' => $credential->notes === null ? null : 'sha1:'.substr(sha1($credential->notes), 0, 12),
            'secret' => $credential->hasSecret() ? AuditService::REDACTED : null,
        ];
    }
}
