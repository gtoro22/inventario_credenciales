<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\CriticalityLevel;
use App\Models\Project;
use App\Models\User;

/**
 * Las credenciales críticas exigen además `credentials.critical` para
 * cualquier operación (ver, crear, editar, eliminar, revelar, copiar).
 */
class CredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('credentials.view');
    }

    public function view(User $user, Credential $credential): bool
    {
        return $user->hasPermission('credentials.view') && $this->reachable($user, $credential);
    }

    public function create(User $user, Project $project, CriticalityLevel $level): bool
    {
        return $user->hasPermission('credentials.create')
            && $user->canAccessProject($project)
            && $this->levelAllowed($user, $level);
    }

    public function update(User $user, Credential $credential): bool
    {
        return $user->hasPermission('credentials.update') && $this->reachable($user, $credential);
    }

    public function delete(User $user, Credential $credential): bool
    {
        return $user->hasPermission('credentials.delete') && $this->reachable($user, $credential);
    }

    public function reveal(User $user, Credential $credential): bool
    {
        return $user->hasPermission('credentials.reveal') && $this->reachable($user, $credential);
    }

    public function copy(User $user, Credential $credential): bool
    {
        return $user->hasPermission('credentials.copy') && $this->reachable($user, $credential);
    }

    public function levelAllowed(User $user, CriticalityLevel $level): bool
    {
        return ! $level->isCritical() || $user->hasPermission('credentials.critical');
    }

    private function reachable(User $user, Credential $credential): bool
    {
        return $user->canAccessProject($credential->project_id)
            && $this->levelAllowed($user, $credential->criticality);
    }
}
