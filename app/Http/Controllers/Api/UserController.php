<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    private const WITH = ['roles:id,slug,name', 'profiles:id,slug,name', 'projects:id,name,code'];

    public function __construct(private UserService $users) {}

    public function index(Request $request)
    {
        $query = User::query()->with(self::WITH);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $request->query('role')));
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return UserResource::collection($query->orderBy('name')->paginate($this->perPage($request))->withQueryString());
    }

    public function store(UserRequest $request): JsonResponse
    {
        return (new UserResource($this->users->create($request->user(), $request->validated())->load(self::WITH)))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('manage', $user);

        return new UserResource($user->load(self::WITH));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        Gate::authorize('manage', $user);

        return new UserResource($this->users->update($request->user(), $user, $request->validated())->load(self::WITH));
    }

    public function destroy(Request $request, User $user): Response
    {
        Gate::authorize('manage', $user);
        $this->users->delete($request->user(), $user);

        return response()->noContent();
    }
}
