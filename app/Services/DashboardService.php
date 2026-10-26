<?php

namespace App\Services;

use App\Models\Credential;
use App\Models\CredentialType;
use App\Models\CriticalityLevel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Métricas del dashboard, siempre acotadas a lo que el usuario puede ver. */
class DashboardService
{
    /** @return array<string, mixed> */
    public function metrics(User $user): array
    {
        $canSeeCredentials = $user->hasPermission('credentials.view');
        $canSeeProjects = $user->hasPermission('projects.view');

        $projects = $canSeeProjects
            ? Project::query()->visibleTo($user)
                ->withCount([
                    'credentials as credentials_count' => fn (Builder $q) => $canSeeCredentials ? $q->visibleTo($user) : $q->whereRaw('1 = 0'),
                    'credentials as critical_count' => fn (Builder $q) => $canSeeCredentials
                        ? $q->visibleTo($user)->whereHas('criticality', fn ($c) => $c->where('slug', 'critical'))
                        : $q->whereRaw('1 = 0'),
                ])
                ->orderBy('name')
                ->get()
            : collect();

        $base = fn (): Builder => $canSeeCredentials && $canSeeProjects
            ? Credential::query()->visibleTo($user)
            : Credential::query()->whereRaw('1 = 0');

        $byCriticality = CriticalityLevel::orderBy('weight')->get()->map(fn ($level) => [
            'slug' => $level->slug,
            'name' => $level->name,
            'color' => $level->color,
            'count' => $base()->where('criticality_level_id', $level->id)->count(),
        ])->values();

        $byType = CredentialType::orderBy('name')->get()->map(fn ($type) => [
            'slug' => $type->slug,
            'name' => $type->name,
            'count' => $base()->where('credential_type_id', $type->id)->count(),
        ])->filter(fn ($row) => $row['count'] > 0)->values();

        $expiring = $base()
            ->with(['project:id,name,code', 'criticality'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(config('vault.expiring_days')))
            ->orderBy('expires_at')
            ->limit(10)
            ->get()
            ->map(fn (Credential $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'project' => $c->project?->name,
                'criticality' => ['slug' => $c->criticality->slug, 'name' => $c->criticality->name, 'color' => $c->criticality->color],
                'expires_at' => $c->expires_at->toDateString(),
                'days_left' => (int) now()->startOfDay()->diffInDays($c->expires_at, false),
            ]);

        return [
            'can_view_credentials' => $canSeeCredentials,
            'totals' => [
                'projects' => $projects->count(),
                'credentials' => $projects->sum('credentials_count'),
                'critical' => $projects->sum('critical_count'),
                'expiring' => $expiring->count(),
            ],
            'projects' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'is_active' => $p->is_active,
                'credentials_count' => $p->credentials_count,
                'critical_count' => $p->critical_count,
            ])->values(),
            'by_project' => $projects->map(fn (Project $p) => ['name' => $p->name, 'count' => $p->credentials_count])->values(),
            'by_criticality' => $byCriticality,
            'by_type' => $byType,
            'expiring' => $expiring->values(),
        ];
    }
}
