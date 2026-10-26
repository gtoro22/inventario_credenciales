<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.view') && $user->canAccessProject($project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.update') && $user->canAccessProject($project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.delete') && $user->canAccessProject($project);
    }
}
