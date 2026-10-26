<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = AuditLog::query()->with('user:id,name');

        // Sin acceso global: solo eventos de sus proyectos y eventos sin proyecto
        // (sesiones, usuarios, roles), que no revelan proyectos ajenos.
        if (! $user->isSuperAdmin()) {
            $query->where(fn ($q) => $q->whereNull('project_id')->orWhereIn('project_id', $user->accessibleProjectIds()));
        }

        foreach (['action', 'user_id', 'project_id', 'entity_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('entity_label', 'like', "%{$search}%")->orWhere('user_email', 'like', "%{$search}%")
                ->orWhere('ip_address', 'like', "%{$search}%")->orWhere('action', 'like', "%{$search}%"));
        }

        return AuditLogResource::collection($query->orderByDesc('id')->paginate($this->perPage($request, 25))->withQueryString());
    }
}
