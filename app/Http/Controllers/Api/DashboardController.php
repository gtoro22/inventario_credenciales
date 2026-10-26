<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function metrics(Request $request, DashboardService $service): JsonResponse
    {
        return response()->json(['data' => $service->metrics($request->user())]);
    }
}
