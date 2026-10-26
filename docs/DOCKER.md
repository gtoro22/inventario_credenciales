# Docker

La aplicación se ejecuta en cuatro contenedores:

| Servicio | Imagen / target | Función |
|---|---|---|
| `db` | `mysql:8.0` | Base de datos MySQL (volumen `db-data`). Sin puerto publicado. |
| `app` | `Dockerfile` → `app` | **Backend** Laravel + PHP-FPM. Espera a MySQL, migra y siembra roles/permisos al arrancar. |
| `web` | `Dockerfile` → `web` | **Frontend + proxy**: nginx sirve los assets React/Ant Design compilados (`public/build`) y reenvía PHP a `app`. |
| `scheduler` | misma imagen que `app` | `schedule:work` (expiración diaria de credenciales). |

> En este proyecto el frontend (React + Inertia) vive dentro de Laravel: se **compila** en una etapa de Node durante el build de la imagen y se **sirve** desde nginx. En desarrollo, un servicio `vite` adicional ofrece recarga en caliente.

## Producción / evaluación

```bash
make init                 # crea .env.docker con APP_KEY, VAULT_KEY y contraseñas aleatorias
# (sin make: cp .env.docker.example .env.docker y rellénalo)
make up                   # docker compose --env-file .env.docker up -d --build
make install              # crea el superadministrador (interactivo)
```
Abre <http://localhost:8080> (`WEB_PORT`). Para datos de ejemplo:
`docker compose --env-file .env.docker exec app php artisan credentials:install --with-demo-data`.

**Respalda `VAULT_KEY`** fuera del servidor: sin ella los secretos guardados no se pueden descifrar. `.env.docker` está en `.gitignore`; nunca se copia a la imagen (`.dockerignore`).

Comandos útiles: `make logs`, `make shell`, `make down` (conserva datos; `docker compose down -v` borra la BD).

## Desarrollo (código montado + HMR)

```bash
make init
docker compose --env-file .env.docker -f compose.yaml -f compose.dev.yaml --profile tools run --rm composer install
make dev                  # app (PHP), web, db y vite (http://localhost:5173)
make test                 # php artisan test dentro del contenedor
```
Abre <http://localhost:8080>; los cambios en PHP y React se reflejan al instante.

## Variables (`.env.docker`)
`APP_KEY`, `VAULT_KEY`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` son obligatorias (compose falla si faltan). Opcionales: `APP_URL`, `WEB_PORT`, `SESSION_SECURE_COOKIE` (true tras HTTPS), `AUTO_MIGRATE`, `VAULT_PREVIOUS_KEYS`, `LOGIN_MAX_ATTEMPTS`.

## Producción real: recomendaciones
* Pon un proxy TLS (Traefik, Caddy, nginx del host o un balanceador) delante de `web` y activa `SESSION_SECURE_COOKIE=true`. Configura `TrustProxies` para que la IP de auditoría sea la real.
* Usa un gestor de secretos (Docker/Swarm secrets, Vault, secretos del orquestador) en lugar de `.env.docker`.
* No publiques el puerto de MySQL; respalda el volumen `db-data` y la clave por separado (`docker compose exec db mysqldump ...`).
* Con varias réplicas de `app`, el estado vive en MySQL (sesión y caché en BD), por lo que escala sin sticky sessions. Ejecuta un solo `scheduler`.

## Notas de verificación
La configuración se validó con `docker compose config` (producción y desarrollo). Las imágenes no pudieron construirse en el entorno donde se escribió (sin daemon de Docker), así que el primer `make up` es la primera construcción real: si algo falla, revisa `docker compose logs app`.
