<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Sirve tanto para Role como para Profile. */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'is_super_admin' => $this->resource instanceof \App\Models\Role && $this->isSuperAdmin(),
            'users_count' => $this->whenCounted('users'),
            'permission_ids' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('id')),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('slug')),
        ];
    }
}
