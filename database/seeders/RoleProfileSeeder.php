<?php

namespace Database\Seeders;

use App\Enums\Permission as P;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleProfileSeeder extends Seeder
{
    public function run(): void
    {
        $all = P::values();

        $roles = [
            Role::SUPER_ADMIN => ['Super Administrador', 'Acceso total e implícito a todo el sistema.', $all],
            'admin' => ['Administrador', 'Gestiona proyectos, credenciales y usuarios. No administra roles.',
                array_values(array_diff($all, [P::RolesManage->value]))],
            'project-manager' => ['Gestor de Proyecto', 'Gestiona proyectos y credenciales no críticas de sus proyectos.', [
                P::DashboardView, P::ProjectsView, P::ProjectsCreate, P::ProjectsUpdate,
                P::CredentialsView, P::CredentialsCreate, P::CredentialsUpdate, P::CredentialsDelete,
                P::CredentialsReveal, P::CredentialsCopy,
            ]],
            'read-only' => ['Usuario de Solo Lectura', 'Consulta proyectos y credenciales enmascaradas.', [
                P::DashboardView, P::ProjectsView, P::CredentialsView,
            ]],
            'auditor' => ['Auditor', 'Consulta la auditoría y los metadatos de credenciales; no ve secretos.', [
                P::DashboardView, P::ProjectsView, P::CredentialsView, P::AuditView, P::CredentialsExport,
            ]],
        ];

        foreach ($roles as $slug => [$name, $description, $perms]) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_system' => true]);
            $this->syncIfNew($role, $perms);
        }

        $profiles = [
            'credential-operator' => ['Operador de credenciales', 'Crear, editar y revelar credenciales no críticas.', [
                P::CredentialsView, P::CredentialsCreate, P::CredentialsUpdate, P::CredentialsReveal, P::CredentialsCopy,
            ]],
            'critical-access' => ['Acceso a credenciales críticas', 'Permite operar credenciales de criticidad crítica.', [
                P::CredentialsCritical,
            ]],
            'security-reviewer' => ['Revisor de seguridad', 'Auditoría y exportación de metadatos.', [
                P::AuditView, P::CredentialsView, P::CredentialsExport,
            ]],
        ];

        foreach ($profiles as $slug => [$name, $description, $perms]) {
            $profile = Profile::updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_system' => true]);
            $this->syncIfNew($profile, $perms);
        }
    }

    /**
     * Asigna permisos solo la primera vez: así volver a sembrar no pisa
     * los ajustes hechos desde la matriz de roles. El super-admin siempre se sincroniza.
     *
     * @param  list<P|string>  $perms
     */
    private function syncIfNew(Role|Profile $model, array $perms): void
    {
        $slugs = array_map(fn ($p) => $p instanceof P ? $p->value : $p, $perms);
        $ids = Permission::whereIn('slug', $slugs)->pluck('id');

        if ($model->wasRecentlyCreated || ($model instanceof Role && $model->isSuperAdmin())) {
            $model->permissions()->sync($ids);
        }
    }
}
