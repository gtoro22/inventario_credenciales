<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CredentialRequest;
use App\Http\Requests\RevealRequest;
use App\Http\Resources\CredentialResource;
use App\Models\Credential;
use App\Models\CriticalityLevel;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\CredentialService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CredentialController extends Controller
{
    private const WITH = ['project:id,name,code', 'type', 'criticality', 'creator:id,name', 'updater:id,name'];

    public function __construct(private CredentialService $credentials, private AuditService $audit) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Credential::class);

        $query = $this->filtered($request)->with(self::WITH);

        $sort = in_array($request->query('sort'), ['name', 'environment', 'status', 'expires_at', 'created_at'], true) ? $request->query('sort') : 'name';
        $query->orderBy($sort, $request->query('direction') === 'desc' ? 'desc' : 'asc');

        return CredentialResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function store(CredentialRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $project = Project::query()->visibleTo($user)->find($data['project_id']);
        if (! $project) {
            throw ValidationException::withMessages(['project_id' => 'El proyecto seleccionado no es válido.']);
        }

        Gate::authorize('create', [Credential::class, $project, CriticalityLevel::findOrFail($data['criticality_level_id'])]);

        $credential = $this->credentials->create($user, $data);

        return (new CredentialResource($credential->load(self::WITH)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $credential): CredentialResource
    {
        $credential = $this->find($request, $credential);
        Gate::authorize('view', $credential);

        return new CredentialResource($credential);
    }

    public function update(CredentialRequest $request, int $credential): CredentialResource
    {
        $user = $request->user();
        $credential = $this->find($request, $credential);
        Gate::authorize('update', $credential);

        $data = $request->validated();

        // Cambiar de proyecto o escalar a "crítica" exige los mismos derechos que crear.
        $project = Project::query()->visibleTo($user)->find($data['project_id'] ?? $credential->project_id);
        if (! $project) {
            throw ValidationException::withMessages(['project_id' => 'El proyecto seleccionado no es válido.']);
        }
        Gate::authorize('create', [Credential::class, $project, CriticalityLevel::findOrFail($data['criticality_level_id'])]);

        return new CredentialResource($this->credentials->update($user, $credential, $data)->load(self::WITH));
    }

    public function destroy(Request $request, int $credential): Response
    {
        $credential = $this->find($request, $credential);
        Gate::authorize('delete', $credential);

        $this->credentials->delete($request->user(), $credential);

        return response()->noContent();
    }

    public function reveal(RevealRequest $request, int $credential): JsonResponse
    {
        $credential = $this->find($request, $credential);
        $purpose = $request->input('purpose', 'view');

        Gate::authorize($purpose === 'copy' ? 'copy' : 'reveal', $credential);

        $secret = $this->credentials->reveal($request->user(), $credential, $purpose);

        return response()->json(['data' => ['id' => $credential->id, 'secret' => $secret]])
            ->header('Cache-Control', 'no-store, private');
    }

    /** Exporta metadatos a CSV. Por diseño NUNCA incluye secretos. */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('credentials.export');

        $query = $this->filtered($request)->with(['project:id,name,code', 'type', 'criticality'])->orderBy('credentials.id');
        $count = (clone $query)->count();

        $this->audit->log('credentials.exported', user: $request->user(), new: ['rows' => $count, 'filters' => $request->only(['search', 'project_id', 'type', 'environment', 'status', 'criticality'])]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'proyecto', 'nombre', 'tipo', 'criticidad', 'entorno', 'estado', 'usuario', 'host', 'puerto', 'expira']);
            $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;

            $query->chunkById(500, function ($rows) use ($out, $safe) {
                foreach ($rows as $c) {
                    fputcsv($out, array_map($safe, [
                        $c->id, $c->project?->code, $c->name, $c->type->name, $c->criticality->name,
                        $c->environment->value, $c->status->value, $c->username, $c->host, $c->port, $c->expires_at?->toDateString(),
                    ]));
                }
            }, 'credentials.id', 'id');

            fclose($out);
        }, 'credenciales-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function find(Request $request, int $id): Credential
    {
        // Fuera del alcance del usuario (proyecto ajeno o crítica sin permiso) => 404.
        return Credential::query()->visibleTo($request->user())->with(self::WITH)->findOrFail($id);
    }

    private function filtered(Request $request): Builder
    {
        $query = Credential::query()->visibleTo($request->user());

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('credentials.name', 'like', "%{$search}%")
                ->orWhere('credentials.username', 'like', "%{$search}%")
                ->orWhere('credentials.host', 'like', "%{$search}%"));
        }

        foreach (['project_id' => 'credentials.project_id', 'environment' => 'credentials.environment', 'status' => 'credentials.status'] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->query($param));
            }
        }

        if ($request->filled('type')) {
            $query->whereHas('type', fn ($q) => $q->where('slug', $request->query('type')));
        }

        if ($request->filled('criticality')) {
            $query->whereHas('criticality', fn ($q) => $q->where('slug', $request->query('criticality')));
        }

        if ($request->boolean('expiring')) {
            $query->where('credentials.status', 'active')->whereNotNull('expires_at')
                ->whereDate('expires_at', '<=', now()->addDays(config('vault.expiring_days')));
        }

        return $query;
    }
}
