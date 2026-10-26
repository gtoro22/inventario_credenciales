<?php

namespace App\Http\Controllers\Api;

use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Http\Controllers\Controller;
use App\Models\CredentialType;
use App\Models\CriticalityLevel;
use Illuminate\Http\JsonResponse;

/** Catálogos para formularios y filtros. */
class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'credential_types' => CredentialType::orderBy('name')->get(['id', 'slug', 'name']),
            'criticality_levels' => CriticalityLevel::orderBy('weight')->get(['id', 'slug', 'name', 'color', 'weight']),
            'environments' => array_map(fn (Environment $e) => ['value' => $e->value, 'label' => $e->label()], Environment::cases()),
            'statuses' => array_map(fn (CredentialStatus $s) => ['value' => $s->value, 'label' => $s->label()], CredentialStatus::cases()),
        ]]);
    }
}
