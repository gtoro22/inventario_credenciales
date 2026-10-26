import axios from 'axios';

/** Cliente de la API interna (/api/*). */
export const api = axios.create({ baseURL: '/api' });

api.interceptors.response.use(
    (res) => res,
    (error) => {
        // Sesión caducada o cuenta desactivada: volver al login.
        if (error.response?.status === 401 && !window.location.pathname.startsWith('/login')) {
            window.location.href = '/login';
        }
        return Promise.reject(error);
    },
);

/** Mensaje legible y errores por campo a partir de un error de axios. */
export function parseError(error) {
    const data = error.response?.data;
    const status = error.response?.status;
    let message = data?.message || 'Ocurrió un error inesperado. Inténtalo de nuevo.';
    if (status === 403) message = 'No tienes permiso para realizar esta acción.';
    if (status === 429) message = data?.message || 'Demasiadas solicitudes. Espera un momento.';
    if (!error.response) message = 'No se pudo conectar con el servidor.';

    const fields = Object.entries(data?.errors || {}).map(([name, errors]) => ({ name: name.split('.')[0], errors }));
    return { message, fields, status };
}

export const fmtDate = (value) => (value ? new Date(value).toLocaleString('es-ES') : '—');
