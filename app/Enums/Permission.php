<?php

namespace App\Enums;

/**
 * Catálogo de permisos base. La tabla `permissions` se siembra desde aquí.
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';
    case ProjectsView = 'projects.view';
    case ProjectsCreate = 'projects.create';
    case ProjectsUpdate = 'projects.update';
    case ProjectsDelete = 'projects.delete';
    case CredentialsView = 'credentials.view';
    case CredentialsCreate = 'credentials.create';
    case CredentialsUpdate = 'credentials.update';
    case CredentialsDelete = 'credentials.delete';
    case CredentialsReveal = 'credentials.reveal';
    case CredentialsCopy = 'credentials.copy';
    case CredentialsExport = 'credentials.export';
    case CredentialsCritical = 'credentials.critical';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case ProfilesManage = 'profiles.manage';
    case AuditView = 'audit.view';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'Ver dashboard',
            self::ProjectsView => 'Ver proyectos',
            self::ProjectsCreate => 'Crear proyectos',
            self::ProjectsUpdate => 'Editar proyectos',
            self::ProjectsDelete => 'Eliminar proyectos',
            self::CredentialsView => 'Ver credenciales',
            self::CredentialsCreate => 'Crear credenciales',
            self::CredentialsUpdate => 'Editar credenciales',
            self::CredentialsDelete => 'Eliminar credenciales',
            self::CredentialsReveal => 'Ver valor secreto de una credencial',
            self::CredentialsCopy => 'Copiar valor secreto',
            self::CredentialsExport => 'Exportar credenciales',
            self::CredentialsCritical => 'Gestionar credenciales críticas',
            self::UsersManage => 'Administrar usuarios',
            self::RolesManage => 'Administrar roles y permisos',
            self::ProfilesManage => 'Administrar perfiles',
            self::AuditView => 'Ver logs de auditoría',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::DashboardView => 'Dashboard',
            self::ProjectsView, self::ProjectsCreate, self::ProjectsUpdate, self::ProjectsDelete => 'Proyectos',
            self::CredentialsView, self::CredentialsCreate, self::CredentialsUpdate, self::CredentialsDelete,
            self::CredentialsReveal, self::CredentialsCopy, self::CredentialsExport, self::CredentialsCritical => 'Credenciales',
            self::UsersManage, self::RolesManage, self::ProfilesManage => 'Administración',
            self::AuditView => 'Auditoría',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
