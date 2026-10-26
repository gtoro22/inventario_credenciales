<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Profile;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index()
    {
        return RoleResource::collection(Profile::with('permissions:id,slug')->withCount('users')->orderBy('id')->get());
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $profile = $this->roles->create($request->user(), Profile::class, $request->validated());

        return (new RoleResource($profile->load('permissions:id,slug')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(RoleRequest $request, Profile $profile): RoleResource
    {
        $this->roles->update($request->user(), $profile, $request->validated());

        return new RoleResource($profile->fresh()->load('permissions:id,slug')->loadCount('users'));
    }

    public function destroy(Request $request, Profile $profile): Response
    {
        $this->roles->delete($request->user(), $profile);

        return response()->noContent();
    }
}
