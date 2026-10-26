<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Páginas Inertia. Los datos se cargan desde la API interna (/api/*). */
class PageController extends Controller
{
    public function dashboard(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->hasPermission('dashboard.view')) {
            return redirect()->route('settings');
        }

        return Inertia::render('Dashboard');
    }

    public function projects(): Response
    {
        return Inertia::render('Projects/Index');
    }

    public function project(Request $request, int $project): Response
    {
        // 404 (no 403) para no revelar la existencia de proyectos ajenos.
        $found = Project::query()->visibleTo($request->user())->findOrFail($project);

        return Inertia::render('Projects/Show', ['projectId' => $found->id]);
    }

    public function credentials(): Response
    {
        return Inertia::render('Credentials/Index');
    }

    public function users(): Response
    {
        return Inertia::render('Users/Index');
    }

    public function roles(): Response
    {
        return Inertia::render('Roles/Index');
    }

    public function profiles(): Response
    {
        return Inertia::render('Profiles/Index');
    }

    public function audit(): Response
    {
        return Inertia::render('Audit/Index');
    }

    public function settings(): Response
    {
        return Inertia::render('Settings/Index');
    }
}
