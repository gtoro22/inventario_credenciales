<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** Nunca incluye el secreto: solo una máscara y la bandera has_secret. */
class CredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $expiringDays = config('vault.expiring_days');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id, 'name' => $this->project->name, 'code' => $this->project->code,
            ]),
            'project_id' => $this->project_id,
            'type' => $this->whenLoaded('type', fn () => ['id' => $this->type->id, 'slug' => $this->type->slug, 'name' => $this->type->name]),
            'credential_type_id' => $this->credential_type_id,
            'criticality' => $this->whenLoaded('criticality', fn () => [
                'id' => $this->criticality->id, 'slug' => $this->criticality->slug,
                'name' => $this->criticality->name, 'color' => $this->criticality->color,
            ]),
            'criticality_level_id' => $this->criticality_level_id,
            'username' => $this->username,
            'host' => $this->host,
            'port' => $this->port,
            'environment' => ['value' => $this->environment->value, 'label' => $this->environment->label()],
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'notes' => $this->notes,
            'expires_at' => $this->expires_at?->toDateString(),
            'expiring_soon' => $this->expires_at !== null
                && $this->status->value === 'active'
                && $this->expires_at->lte(now()->addDays($expiringDays)),
            'has_secret' => $this->hasSecret(),
            'secret' => $this->hasSecret() ? '********' : null,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'updated_by' => $this->whenLoaded('updater', fn () => $this->updater?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'abilities' => [
                'update' => Gate::forUser($user)->allows('update', $this->resource),
                'delete' => Gate::forUser($user)->allows('delete', $this->resource),
                'reveal' => Gate::forUser($user)->allows('reveal', $this->resource),
                'copy' => Gate::forUser($user)->allows('copy', $this->resource),
            ],
        ];
    }
}
