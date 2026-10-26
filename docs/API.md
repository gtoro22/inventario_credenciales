# Documentación de la API interna

Base: `/api`. Autenticación por **sesión** (cookie) + **CSRF** (`X-XSRF-TOKEN`, que axios envía solo desde la cookie `XSRF-TOKEN`).
Respuestas JSON (`Accept: application/json`). Todos los endpoints `/api/*` exigen usuario autenticado **y activo**, más el permiso indicado.

## Convenciones

| Caso | Forma |
|---|---|
| Recurso | `{ "data": { ... } }` |
| Colección paginada | `{ "data": [...], "links": {...}, "meta": { "current_page", "last_page", "per_page", "total" } }` — `?page=`, `?per_page=` (1–100) |
| Error | `{ "message": "..." }` |
| Validación (422) | `{ "message": "...", "errors": { "campo": ["..."] } }` |

Códigos: `200` OK · `201` creado · `204` eliminado · `401` no autenticado · `403` sin permiso · `404` no existe **o no visible para el usuario** · `422` validación/regla de negocio · `429` límite de peticiones o bloqueo de login.

> Un proyecto o credencial fuera del alcance del usuario responde `404`, nunca `403`, para no revelar su existencia.

## Autenticación (rutas web)

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/login` | `email`, `password`, `remember?`. Con `Accept: application/json` devuelve `{data: perfil}`; desde el navegador redirige. 422 credenciales inválidas, 429 bloqueo progresivo. |
| POST | `/logout` | Cierra sesión (auditado). |

```http
POST /login
{"email":"ana@empresa.com","password":"********"}
→ 200 {"data":{"id":2,"name":"Ana","email":"ana@empresa.com","is_super_admin":false,"permissions":["projects.view"],"roles":[],"profiles":[],"project_ids":[1,3]}}
```

## Endpoints

| Método | Ruta | Permiso | Notas |
|---|---|---|---|
| GET | `/api/me` | autenticado | Perfil y permisos efectivos. |
| PUT | `/api/me/password` | autenticado | `current_password`, `password`, `password_confirmation`. |
| GET | `/api/meta` | autenticado | Catálogos: tipos, niveles de criticidad, entornos, estados. |
| GET | `/api/dashboard/metrics` | `dashboard.view` | Totales, tarjetas de proyectos y series de gráficas, acotados al usuario. |
| GET | `/api/projects` | `projects.view` | Filtros `search`, `status=active\|inactive`, `tag`, `sort`, `direction`. |
| POST | `/api/projects` | `projects.create` | El creador recibe acceso y es responsable por defecto. |
| GET | `/api/projects/{id}` | `projects.view` | Incluye `metrics`. |
| PUT | `/api/projects/{id}` | `projects.update` | |
| PATCH | `/api/projects/{id}/active` | `projects.update` | `{ "is_active": bool }` |
| DELETE | `/api/projects/{id}` | `projects.delete` | Solo si está inactivo; elimina (soft) sus credenciales. |
| GET | `/api/credentials` | `credentials.view` | Filtros `search`, `project_id`, `type` (slug), `environment`, `status`, `criticality` (slug), `expiring=1`, `sort`, `direction`. |
| POST | `/api/credentials` | `credentials.create` | Requiere además `credentials.critical` si la criticidad es Crítica. |
| GET | `/api/credentials/{id}` | `credentials.view` | Secreto siempre enmascarado. |
| PUT | `/api/credentials/{id}` | `credentials.update` | `secret` vacío/ausente conserva el actual. |
| DELETE | `/api/credentials/{id}` | `credentials.delete` | |
| POST | `/api/credentials/{id}/reveal` | `credentials.reveal` (`purpose=view`) o `credentials.copy` (`purpose=copy`) | Requiere `confirm: true`. Auditado. Límite 30/min. |
| GET | `/api/credentials/export` | `credentials.view` + `credentials.export` | CSV de metadatos, **sin secretos**. Auditado. |
| GET, POST, GET, PUT, DELETE | `/api/users[/{id}]` | `users.manage` | |
| GET | `/api/roles`, `/api/profiles`, `/api/permissions` | `roles.manage` ó `users.manage` ó `profiles.manage` | |
| POST, PUT, DELETE | `/api/roles[/{id}]` | `roles.manage` | |
| POST, PUT, DELETE | `/api/profiles[/{id}]` | `profiles.manage` | |
| GET | `/api/audit-logs` | `audit.view` | Filtros `action`, `user_id`, `project_id`, `entity_type`, `from`, `to`, `search`. |

## Ejemplos

**Crear credencial**
```http
POST /api/credentials
{"project_id":1,"credential_type_id":4,"criticality_level_id":3,"name":"DB principal","username":"app",
 "secret":"<valor>","host":"db.interno","port":3306,"environment":"production","status":"active",
 "expires_at":"2027-01-31","notes":"Rotar cada trimestre"}

→ 201
{"data":{"id":12,"name":"DB principal","project":{"id":1,"name":"Portal","code":"PORTAL"},
 "type":{"id":4,"slug":"database","name":"Conexión a base de datos"},
 "criticality":{"id":3,"slug":"high","name":"Alta","color":"orange"},
 "environment":{"value":"production","label":"Producción"},"status":{"value":"active","label":"Activa"},
 "has_secret":true,"secret":"********","expiring_soon":false,
 "abilities":{"update":true,"delete":true,"reveal":true,"copy":true}}}
```

**Revelar secreto**
```http
POST /api/credentials/12/reveal
{"confirm":true,"purpose":"view"}
→ 200  (Cache-Control: no-store)
{"data":{"id":12,"secret":"<valor en claro>"}}

POST /api/credentials/12/reveal  {}            → 422 {"message":"...","errors":{"confirm":["Debes confirmar explícitamente..."]}}
POST /api/credentials/12/reveal  (sin permiso) → 403 {"message":"No tienes permiso para realizar esta acción."}
```

**Listado paginado**
```http
GET /api/credentials?project_id=1&environment=production&per_page=2
→ {"data":[{},{}],"links":{"first":"...","next":"..."},"meta":{"current_page":1,"last_page":3,"per_page":2,"total":5}}
```

**Dashboard**
```json
{"data":{"can_view_credentials":true,
 "totals":{"projects":3,"credentials":15,"critical":3,"expiring":6},
 "projects":[{"id":1,"name":"Portal","code":"PORTAL","is_active":true,"credentials_count":5,"critical_count":1}],
 "by_project":[{"name":"Portal","count":5}],
 "by_criticality":[{"slug":"low","name":"Baja","color":"green","count":3}],
 "by_type":[{"slug":"database","name":"Conexión a base de datos","count":3}],
 "expiring":[{"id":7,"name":"API notificaciones","project":"Portal","expires_at":"2026-11-07","days_left":12,"criticality":{"slug":"medium","name":"Media","color":"gold"}}]}}
```

**Error de validación**
```json
{"message":"The code has already been taken.","errors":{"code":["The code has already been taken."]}}
```
