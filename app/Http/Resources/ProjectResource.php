<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'owner_id' => $this->owner_id,
            'tags' => $this->tags ?? [],
            'credentials_count' => $this->whenCounted('credentials'),
            'critical_count' => $this->when(isset($this->critical_count), fn () => (int) $this->critical_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'abilities' => [
                'update' => Gate::forUser($user)->allows('update', $this->resource),
                'delete' => Gate::forUser($user)->allows('delete', $this->resource),
            ],
        ];
    }
}
