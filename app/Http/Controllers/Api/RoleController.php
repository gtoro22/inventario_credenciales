<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index()
    {
        return RoleResource::collection(Role::with('permissions:id,slug')->withCount('users')->orderBy('id')->get());
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = $this->roles->create($request->user(), Role::class, $request->validated());

        return (new RoleResource($role->load('permissions:id,slug')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(RoleRequest $request, Role $role): RoleResource
    {
        $this->roles->update($request->user(), $role, $request->validated());

        return new RoleResource($role->fresh()->load('permissions:id,slug')->loadCount('users'));
    }

    public function destroy(Request $request, Role $role): Response
    {
        $this->roles->delete($request->user(), $role);

        return response()->noContent();
    }
}
