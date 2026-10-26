import { usePage } from '@inertiajs/react';

/** Permisos efectivos del usuario autenticado (solo para UX; el backend siempre valida). */
export function useAuth() {
    const user = usePage().props.auth.user;
    const set = new Set(user?.permissions ?? []);
    const can = (permission) => Boolean(user?.is_super_admin) || set.has(permission);
    const canAny = (...permissions) => permissions.some(can);

    return { user, can, canAny };
}
