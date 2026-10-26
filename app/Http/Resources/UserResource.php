<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($r) => ['id' => $r->id, 'slug' => $r->slug, 'name' => $r->name])),
            'profiles' => $this->whenLoaded('profiles', fn () => $this->profiles->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name])),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'code' => $p->code])),
            'is_super_admin' => $this->whenLoaded('roles', fn () => $this->roles->contains('slug', 'super-admin')),
        ];
    }
}
