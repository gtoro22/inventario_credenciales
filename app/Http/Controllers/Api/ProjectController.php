<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Credential;
use App\Models\Project;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Project::class);
        $user = $request->user();
        $canCount = $user->hasPermission('credentials.view');

        $query = Project::query()->visibleTo($user)->with('owner:id,name')
            ->withCount([
                'credentials as credentials_count' => fn (Builder $q) => $canCount ? $q->visibleTo($user) : $q->whereRaw('1 = 0'),
                'credentials as critical_count' => fn (Builder $q) => $canCount
                    ? $q->visibleTo($user)->whereHas('criticality', fn ($c) => $c->where('slug', 'critical'))
                    : $q->whereRaw('1 = 0'),
            ]);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }

        match ($request->query('status')) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $sort = in_array($request->query('sort'), ['name', 'code', 'created_at', 'credentials_count'], true) ? $request->query('sort') : 'name';
        $query->orderBy($sort, $request->query('direction') === 'desc' ? 'desc' : 'asc');

        return ProjectResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function store(ProjectRequest $request): JsonResponse
    {
        Gate::authorize('create', Project::class);
        $actor = $request->user();

        $project = DB::transaction(function () use ($request, $actor) {
            $project = Project::create([...$request->validated(), 'owner_id' => $request->validated('owner_id') ?? $actor->id]);

            // Quien crea (y quien es responsable) conserva acceso al proyecto.
            $project->users()->syncWithoutDetaching(array_filter([$actor->id, $project->owner_id]));
            $actor->flushPermissionCache();

            $this->audit->log('project.created', $project, [], $this->snapshot($project), user: $actor);

            return $project;
        });

        return (new ProjectResource($project->load('owner:id,name')->loadCount('credentials')))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $project): ProjectResource
    {
        $user = $request->user();
        $project = Project::query()->visibleTo($user)->with('owner:id,name')->findOrFail($project);
        Gate::authorize('view', $project);

        $visible = fn (): Builder => $user->hasPermission('credentials.view')
            ? Credential::query()->visibleTo($user)->where('project_id', $project->id)
            : Credential::query()->whereRaw('1 = 0');

        $project->credentials_count = $visible()->count();
        $project->critical_count = $visible()->whereHas('criticality', fn ($q) => $q->where('slug', 'critical'))->count();

        return (new ProjectResource($project))->additional(['metrics' => [
            'by_criticality' => $visible()->join('criticality_levels as cl', 'cl.id', '=', 'credentials.criticality_level_id')
                ->selectRaw('cl.slug, cl.name, cl.color, cl.weight, count(*) as count')->groupBy('cl.slug', 'cl.name', 'cl.color', 'cl.weight')
                ->orderBy('cl.weight')->get(),
            'by_type' => $visible()->join('credential_types as ct', 'ct.id', '=', 'credentials.credential_type_id')
                ->selectRaw('ct.slug, ct.name, count(*) as count')->groupBy('ct.slug', 'ct.name')->get(),
            'by_status' => $visible()->selectRaw('credentials.status as status, count(*) as count')->groupBy('credentials.status')->get(),
            'expiring' => $visible()->where('status', 'active')->whereNotNull('expires_at')
                ->whereDate('expires_at', '<=', now()->addDays(config('vault.expiring_days')))->count(),
        ]]);
    }

    public function update(ProjectRequest $request, int $project): ProjectResource
    {
        $project = Project::query()->visibleTo($request->user())->findOrFail($project);
        Gate::authorize('update', $project);

        DB::transaction(function () use ($request, $project) {
            $before = $this->snapshot($project);
            $project->update($request->validated());

            if ($project->owner_id) {
                $project->users()->syncWithoutDetaching([$project->owner_id]);
            }

            [$old, $new] = $this->audit->diff($before, $this->snapshot($project->fresh()));
            if ($old !== [] || $new !== []) {
                $this->audit->log('project.updated', $project, $old, $new, user: $request->user());
            }
        });

        return new ProjectResource($project->load('owner:id,name')->loadCount('credentials'));
    }

    public function setActive(Request $request, int $project): ProjectResource
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $project = Project::query()->visibleTo($request->user())->findOrFail($project);
        Gate::authorize('update', $project);

        $project->update(['is_active' => $data['is_active']]);
        $this->audit->log($data['is_active'] ? 'project.activated' : 'project.deactivated', $project, user: $request->user());

        return new ProjectResource($project->load('owner:id,name'));
    }

    public function destroy(Request $request, int $project): Response
    {
        $project = Project::query()->visibleTo($request->user())->findOrFail($project);
        Gate::authorize('delete', $project);

        // Eliminación controlada: primero debe desactivarse explícitamente.
        if ($project->is_active) {
            throw ValidationException::withMessages(['project' => 'Desactiva el proyecto antes de eliminarlo.']);
        }

        DB::transaction(function () use ($request, $project) {
            $count = $project->credentials()->count();
            $this->audit->log('project.deleted', $project, $this->snapshot($project) + ['credentials_deleted' => $count], user: $request->user());
            $project->credentials()->delete();
            $project->delete();
        });

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function snapshot(Project $project): array
    {
        return [
            'name' => $project->name,
            'code' => $project->code,
            'description' => $project->description,
            'is_active' => $project->is_active,
            'owner_id' => $project->owner_id,
            'tags' => $project->tags ?? [],
        ];
    }
}
