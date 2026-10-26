<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Permission::orderBy('group')->orderBy('id')->get(['id', 'slug', 'name', 'group'])]);
    }
}
