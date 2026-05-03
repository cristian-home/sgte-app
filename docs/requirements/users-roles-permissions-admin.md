---
name: users-roles-permissions-admin
type: feat
scope: admin
status: completed
priority: high
created_date: 2026-05-03
completed_date: 2026-05-03
srs_refs: []
migration_strategy: modify-existing
---

# Administración — Usuarios, Roles y Permisos

## Description

El handoff de diseño en `docs/_drafts/rbac_control_design_handoff_sgte_admin/` describe el área **Administración** de SGTE: tres pantallas conectadas (`/users`, `/roles`, `/roles/{role}`) más una pantalla de referencia read-only (`/permissions`) y dos modales (Create/Edit usuario, Delete confirmación). Es la piedra angular de la administración RBAC del sistema.

El estado actual del código tiene un `UserController` con CRUD básico de un solo rol por usuario, sin filtros, sin Estado activo, sin Último acceso, y sin pantallas de Roles/Permisos. Este requerimiento reemplaza la implementación mínima por la versión del handoff: lista filtrable + multi-rol + activación + matriz de permisos editable por rol + audit trail + protecciones de self-action.

**Ámbito puntual:**

1. **Schema**: agregar `users.is_active` (boolean) y `users.last_login_at` (timestampTz nullable). Agregar `roles.description` (text nullable) editando la migración primaria de spatie/permission. Esta app está en desarrollo temprano y el seeder de catálogos se re-aplica con `migrate:fresh --seed` — modificar las migraciones primarias en lugar de crear backfills.
2. **Multi-rol**: `UserStoreRequest` y `UserUpdateRequest` aceptan `roles[]` (array, mínimo 1, valores en el enum `Role` excluyendo `super_admin`). `syncRoles($validated['roles'])` reemplaza la asignación singular actual.
3. **Permission keys**: el enum `Permission` actual y sus 53 valores **NO cambian**. Agrupación + orden + etiquetas se introducen vía un nuevo enum `PermissionGroup` y dos métodos en el enum existente: `Permission::group(): PermissionGroup` y un `Permission::groupedForUi(): array` helper estático. La UI (`/permissions` y matriz de roles) consume esa estructura.
4. **Modales**: las pages `resources/js/pages/users/create.tsx`, `edit.tsx`, `show.tsx` se eliminan. Las acciones de Crear/Editar/Eliminar se exponen desde `/users` vía Dialog (Crear/Editar) y AlertDialog (Eliminar). Los métodos `create`, `edit`, `show` del `UserController` se eliminan; las rutas `users.create`, `users.edit`, `users.show` desaparecen del `Route::resource` (vía `->only([...])`).
5. **Sidebar IA**: el grupo Administración pasa de 3 a 5 items: **Usuarios / Roles / Permisos / Auditoría / Importaciones** (en ese orden).
6. **Login flow**:
   - Listener en `Illuminate\Auth\Events\Login` escribe `last_login_at = now()`.
   - Custom Fortify `authenticate using` callback (o `LoginRequest::authenticate()` Fortify hook) rechaza login si `is_active === false`, con error `'Esta cuenta está desactivada. Contacta a un administrador.'`.
7. **Acciones de usuario**:
   - **Editar** → modal A (modo edit).
   - **Restablecer contraseña** → POST `users.reset-password` setea `password = Hash::make(Str::password(16))` + `must_change_password = true` y dispara `Fortify::forgotPassword()` por email.
   - **Desactivar/Activar** → PATCH `users.toggle-active` invierte `is_active`.
   - **Eliminar** → modal B (AlertDialog).
8. **Self-protection** (centralizado en `UserUpdateRequest::after()` + reglas en endpoints de toggle/delete):
   - No puedes eliminar tu propia cuenta (ya existe).
   - No puedes desactivar tu propia cuenta.
   - No puedes quitarte el último rol de admin del sistema (si eres el último admin con permisos administrativos, los roles no pueden cambiarse a un set sin admin).
   - Solo un super_admin puede modificar un usuario que tenga el rol super_admin.
9. **Audit log**:
   - `UserController@update`: si los roles cambian, escribir `activity()->withProperties(['old_roles' => [...], 'new_roles' => [...]])->log('roles_synced')`.
   - `RoleController@update`: si los permisos cambian, escribir activity sobre el modelo `Spatie\Permission\Models\Role` con `properties.added`/`properties.removed`.
   - El cambio de `description` del rol queda registrado vía `LogsActivity` trait que se agregará al modelo `App\Models\Role` (extensión local de SpatieRole).
   - El cambio de `is_active` queda registrado automáticamente: agregar `is_active` y `last_login_at` (excluyendo este último explícitamente — los logins no deberían contaminar el audit log) al `getActivitylogOptions().logOnly([...])` del modelo `User`.

**Out of scope:**

- **Crear/eliminar roles personalizados** ("Nuevo rol" del prototipo): los 5 roles del enum son fijos. El botón "Nuevo rol" del prototipo NO se renderiza (el card del prototipo es solo decorativo); el header de `/roles` muestra solo el título y subtítulo, sin CTA primario.
- **Editar nombre/etiqueta del rol**: solo `description` y los permisos asignados son mutables. La pantalla `/roles/{role}` muestra "Nombre" y "Etiqueta" como read-only (sin lápiz de inline-edit), solo "Descripción" tiene click-to-edit.
- **Cambio de tema (dark mode), notificaciones, búsqueda global ⌘K**: ya existen en el shell — no se tocan.
- **Avatares con foto**: el handoff usa avatares deterministas con OKLCH hue por id + iniciales. Mantenemos esto (no se agrega columna `avatar_url`).
- **Página `/users/{id}` (perfil completo)**: la acción "Ver perfil" del dropdown se omite del menú de fila. Si el usuario quiere editar, abre el modal de edición.
- **Permission keys re-key**: NO se renombra ningún case del enum `Permission`. Las claves `vehicles.update`, `third-parties.view`, `incidents.create`, `services.update-projected` se mantienen tal cual; el handoff de diseño es la guía visual de orden y agrupación, no la fuente de IDs.
- **Empty-state ilustración custom**: el placeholder "ilustración" del handoff se reemplaza por un `lucide-react` icon centrado + texto.
- **`/users/{id}/profile`** y route helpers asociados: se elimina.

## Acceptance Criteria

### Lista de Usuarios — `/users`

- [x] **AC1**: WHEN un admin navega a `/users` THEN la página renderiza usando `<DataTable>` + `useServerTable` con paginación server-side (10/25/50 por página), sort por `name|email|last_login_at|created_at`, y los filtros declarados en AC3.
- [x] **AC2**: WHEN la respuesta del controller llega THEN cada fila incluye: `id`, `name`, `email`, `roles: [{id, name, label}]` (label viene del enum Role), `last_login_at` (ISO 8601 o `null`), `is_active` (bool), `created_at`. Avatar se calcula client-side (iniciales + hue OKLCH determinista por `id`).
- [x] **AC3**: WHEN el usuario aplica `filter[search]=cami` THEN solo quedan filas con `name ILIKE '%cami%'` o `email ILIKE '%cami%'`. WHEN aplica `filter[roles]=admin,operator` THEN solo quedan filas que tienen ALMENOS UNO de esos roles. WHEN aplica `filter[is_active]=true` THEN solo quedan activos. Los tres filtros son combinables.
- [x] **AC4**: WHEN cualquier filtro está activo THEN aparece un botón ghost `<X /> Limpiar filtros` que resetea los filtros y la página a 1.
- [x] **AC5**: WHEN la tabla renderiza THEN las columnas son, en orden: **Nombre** (avatar 32px + nombre semibold), **Correo** (muted, tabular-nums), **Roles** (Badge pills — `super_admin` con variante `default/primary`, los demás `outline/secondary`), **Último acceso** (muted, formateado en es-CO via `date-fns formatDistanceToNow` o equivalente — "Hace 14 min", "Hoy · 08:17", "Ayer · 17:54"; `null` muestra "Nunca"), **Estado** (Switch + Badge "Activo" success / "Inactivo" muted), **Acciones** (DropdownMenu desde `<MoreHorizontal />`).
- [x] **AC6**: WHEN un usuario tiene `is_active = false` THEN la fila renderiza con `opacity-72`.
- [x] **AC7**: WHEN el dropdown de fila se abre THEN contiene 4 items + 1 separador + 1 destructive: `<Pencil /> Editar`, `<KeyRound /> Restablecer contraseña`, `<LogOut/> Desactivar` o `<Check /> Activar` (dinámico según `is_active`), separator, `<Trash2 /> Eliminar` (variante destructive). El item "Ver perfil" del prototipo NO se incluye.
- [x] **AC8**: WHEN el usuario hace click en el switch de la columna Estado THEN se dispara PATCH `/users/{user}/active` (route name `users.toggle-active`) optimísticamente. Si el response es 422 (self-action), el switch revierte y se muestra un toast.
- [x] **AC9**: WHEN el botón "Nuevo usuario" del header se clickea THEN se abre el modal `<UserDialog mode="create" />`. WHEN se clickea "Editar" en una fila THEN se abre el modal `<UserDialog mode="edit" user={row} />`. WHEN se clickea "Eliminar" THEN se abre `<DeleteUserDialog user={row} />`.
- [x] **AC10**: WHEN no hay resultados que coincidan con los filtros THEN se renderiza un empty-state centrado: icono `<SearchX />` 48px muted, título "Sin resultados" 17px 600, subtítulo muted "Ajusta los filtros o crea un nuevo usuario.", y dos botones (ghost "Limpiar filtros" + primary "Nuevo usuario"). WHEN no hay usuarios en absoluto (count total = 0, sin filtros) THEN icono `<Users />`, título "Aún no hay usuarios", subtítulo, y un solo botón primary "Nuevo usuario".
- [x] **AC11**: WHEN el usuario está en `/users?role=admin` (deep link desde `/roles/{id}` "Ver todos") THEN el filtro de rol se pre-aplica con ese valor.

### Modal A — Crear/Editar usuario (Dialog)

- [x] **AC12**: WHEN el modal se abre en modo `create` THEN el header muestra "Nuevo usuario" / "Crea una cuenta para que alguien acceda al sistema." y los campos son: Nombre completo, Correo electrónico, Contraseña (con eye/eye-off toggle + medidor de fuerza de 4 segmentos), Confirmar contraseña, Roles (combobox multi-select), Switch "Cuenta activa" (default ON), Checkbox "Enviar correo de bienvenida" (default OFF). En modo `edit` los campos de password se OCULTAN (no se cambian desde aquí — para eso está "Restablecer contraseña").
- [x] **AC13**: WHEN el form se envía en modo create THEN se hace POST `users.store` con `{name, email, password, roles[], is_active, send_welcome_email}`. WHEN en modo edit THEN se hace PUT `users.update` con `{name, email, roles[], is_active}` (sin password).
- [x] **AC14**: WHEN `send_welcome_email=true` THEN tras crear el usuario, se dispara `Notification::send($user, new WelcomeUserNotification($temporaryPassword))` con instrucciones para configurar contraseña — el password creado por el admin se descarta y se reemplaza por `Str::password(16)` antes de enviar el email; el usuario tiene `must_change_password=true` y el primer login lo redirige a `/settings/password`.
- [x] **AC15**: WHEN la lista de roles renderiza THEN aparecen los 4 roles asignables: Administrador, Operación, Conductor, Contabilidad. **Super Administrador NO aparece** en la lista (se bootstrappea por env y bypassa todos los gates — no se asigna por UI).
- [x] **AC16**: WHEN el form valida client-side THEN: `name` no vacío, `email` contiene `@`, `password.length >= 8`, `password === confirm_password`, `roles.length >= 1`. El botón "Guardar usuario" / "Guardar cambios" queda disabled hasta que todas las reglas pasen.
- [x] **AC17**: WHEN el password se va llenando THEN el meter de 4 segmentos muestra: 0=vacío, 1=rojo (Débil), 2=warning (Aceptable), 3=warning (Buena), 4=success (Fuerte). Score = `(length>=8) + has_upper + has_digit + has_special`.

### Modal B — Eliminar usuario (AlertDialog)

- [x] **AC18**: WHEN el modal se abre THEN renderiza un AlertDialog 440px max con icono destructive `<Trash2 />`, título "¿Eliminar usuario?", body "Se eliminará permanentemente la cuenta de **{name}** ({email}). Esta acción no se puede deshacer y el usuario perderá acceso inmediato al sistema.", y 2 botones: ghost "Cancelar" + destructive "Eliminar".
- [x] **AC19**: WHEN se confirma el delete THEN se hace DELETE `users.destroy`. Si el usuario es el actualmente logueado, el backend retorna 422 con `errors.user = 'No puedes eliminar tu propia cuenta.'` (ya existe). Si es el último admin, retorna 422 con `errors.user = 'No puedes eliminar al último administrador del sistema.'`.

### Lista de Roles — `/roles`

- [x] **AC20**: WHEN un admin navega a `/roles` THEN la página renderiza un grid responsivo (`grid-cols-[repeat(auto-fill,minmax(320px,1fr))] gap-4`) con 5 cards (uno por rol del enum, en orden: Super Admin, Administrador, Operación, Conductor, Contabilidad).
- [x] **AC21**: WHEN una card de rol renderiza THEN muestra: icono cuadrado redondeado 40px (`<ShieldCheck />` con bg `primary` para Super Admin, `<Shield />` con bg `muted` para los otros) + nombre del rol (h3 17px 600) + Tooltip con `<Lock />` "Este rol omite todas las verificaciones de permisos." (solo Super Admin) + etiqueta monospace 11.5px muted abajo del nombre. Descripción del rol (paragraph 13px muted, min-height 40px). Separator. Stats row: `users_count` y `permissions_count` (20px 600 tabular-nums) con caption muted "usuarios" / "permisos". Footer: outline `Editar` (deshabilitado y con tooltip "Bloqueado" para Super Admin, navega a `/roles/{role}` para los otros) + ghost "Ver detalles" (navega también a `/roles/{role}`).
- [x] **AC22**: WHEN el header de `/roles` renderiza THEN muestra h1 "Roles" + subtítulo "Define qué puede hacer cada conjunto de usuarios dentro del sistema.". **No** se renderiza un botón "Nuevo rol" (los roles son fijos).

### Detalle de Rol — `/roles/{role}`

- [x] **AC23**: WHEN un admin navega a `/roles/{role}` (route key = role name del enum, e.g. `admin`, `operator`) THEN se renderiza la pantalla en grid `[340px_1fr] gap-4 items-start`. Si el role name no existe en el enum, 404.
- [x] **AC24**: WHEN la columna izquierda renderiza THEN contiene 2 cards en stack:
  - **Información del rol**: Nombre (read-only), Etiqueta monospace pill (read-only), Descripción (click-to-edit textarea con lápiz hint, commit en blur o Enter, autosize), Separator, stats row con `permissions_count` (live desde el set actual) + `users_count`. Para Super Admin todos los campos son read-only y el lápiz de Descripción se OCULTA.
  - **Usuarios con este rol**: header "Usuarios con este rol" + count muted "{N} usuarios" + ghost link "Ver todos" `<ChevronRight />` que navega a `/users?roles={role.name}`. Body: stack de avatares 28px (iniciales + hue OKLCH) con border 2px del card-color y `-8px` de overlap, máximo 6 visibles + caption "+N más" si hay más. Empty: "Aún no hay usuarios con este rol.".
- [x] **AC25**: WHEN la columna derecha renderiza THEN es un Card "Permisos" con header dinámico:
  - Para Super Admin: "Este rol omite las verificaciones de permisos y tiene acceso completo." (sin acciones).
  - Para los otros: "{N} permisos activos de {TOTAL}." + acciones ghost "Expandir todo" / "Contraer".
- [x] **AC26**: WHEN la matriz de permisos renderiza THEN está agrupada en 17 grupos según `PermissionGroup` enum (orden y labels en español, ver §Technical / Enums abajo). Cada group row es colapsable: chevron rotado, label 500 weight, badge muted con `{on}/{all}` count, ghost xs button "Marcar todo" / "Desmarcar todo" (oculto para Super Admin). Las filas de permiso muestran label (14px 500) + descripción (12.5px muted) y a la derecha un Switch.
- [x] **AC27**: WHEN el usuario togglea cualquier switch (no Super Admin) THEN se compara contra el `baseline` y `dirty = current !== baseline`. Si `dirty`, aparece sticky save bar fijada al fondo de la viewport, full width, `bg-card`, `border-t`, padding `12px 24px`, box-shadow superior:
  - Izquierda: dot amber 8px + "{N} cambio(s) sin guardar" + caption muted "+{added} −{removed}".
  - Derecha: ghost "Descartar" `<RotateCcw />` (resetea `current = baseline`) + primary "Guardar cambios" `<Check />` (POST `roles.update`).
- [x] **AC28**: WHEN el usuario togglea "Marcar todo" sobre un grupo THEN: si todos los permisos del grupo están on → desmarca todos; si alguno está off → marca todos. El badge `{on}/{all}` actualiza en vivo.
- [x] **AC29**: WHEN se guardan cambios THEN PUT `roles.update` payload `{description: string|null, permissions: string[]}` sincroniza ambos. La sticky bar desaparece al volver a `dirty=false`.
- [x] **AC30**: WHEN el rol es Super Admin THEN: todos los switches están disabled (`opacity-55 cursor-not-allowed`), la sticky bar nunca aparece, el endpoint `roles.update` retorna 403 si se intenta modificar.

### Permisos — `/permissions` (read-only)

- [x] **AC31**: WHEN un admin navega a `/permissions` THEN se renderiza una página de referencia con tabs (Usuarios | Roles | Permisos | badge muted "Referencia") con Permisos activo, header h1 "Permisos" + subtítulo "Referencia de todos los permisos disponibles en el sistema, agrupados por módulo.".
- [x] **AC32**: WHEN la página renderiza THEN un Alert con `<AlertTriangle />` muted-warning fija al inicio: "**Solo lectura.** Los permisos son definidos por la plataforma. Para otorgarlos o revocarlos, edita un rol desde la pestaña [Roles](/roles)." (link interno con Inertia `<Link>`).
- [x] **AC33**: WHEN el body renderiza THEN se muestra un grid `grid-cols-1 md:grid-cols-2 gap-4` de cards, una por grupo. Cada card: header con label del grupo + Badge muted con `{count}` total. Body: stack de filas: label (14px 500) + key monospace 12px muted (e.g. `vehicles.view`).

### Tabs compartidos

- [x] **AC34**: WHEN cualquiera de las tres pantallas (`/users`, `/roles`, `/permissions`) renderiza THEN justo debajo del header (h1 + subtítulo) hay un componente `<AdminTabs current="users|roles|permissions" />` que renderiza shadcn `Tabs` con `<TabsList>` Usuarios | Roles | Permisos + a la derecha un Badge muted small "Referencia". Click en cualquier tab navega vía `<Link>` Inertia a la página correspondiente. La pantalla `/roles/{role}` también renderiza con tab "Roles" activo.

### Sidebar

- [x] **AC35**: WHEN el sidebar renderiza para admin THEN el grupo "Administración" tiene 5 items en este orden: **Usuarios** (`/users`), **Roles** (`/roles`), **Permisos** (`/permissions`), **Auditoría** (`/audit-log`), **Importaciones** (`/admin/imports`). Los items respetan los permission gates existentes (`VIEW_USERS`, `VIEW_AUDIT_LOG`, `MANAGE_DATA_IMPORTS`).

### Schema y login flow

- [x] **AC36**: WHEN se ejecuta `php artisan migrate:fresh --seed` THEN la tabla `users` tiene las columnas `is_active boolean NOT NULL DEFAULT true` y `last_login_at timestamp(0) WITH TIME ZONE NULLABLE`. La tabla `roles` (de spatie permission) tiene una columna `description text NULLABLE`.
- [x] **AC37**: WHEN un usuario inicia sesión exitosamente THEN su `last_login_at` se actualiza a `now()`. Implementación: `App\Listeners\UpdateLastLoginAt` registrado en `AppServiceProvider::boot()` escuchando `Illuminate\Auth\Events\Login`.
- [x] **AC38**: WHEN un usuario con `is_active = false` intenta iniciar sesión THEN la autenticación falla con error genérico "Esta cuenta está desactivada. Contacta a un administrador.". Implementación: hook en Fortify `authenticate using` (cierre custom en `FortifyServiceProvider::boot()`) que verifica credenciales + chequea `is_active`. Si las creds son válidas pero `is_active=false`, retorna `null`. Asegurar que el mensaje genérico no filtre si la cuenta existe.
- [x] **AC39**: WHEN un admin desactiva un usuario actualmente logueado en otra sesión THEN su próxima request HTTP es interceptada y redirigida a `/login` con error "Esta cuenta está desactivada.". Implementación: middleware `EnsureUserIsActive` registrado globalmente en `bootstrap/app.php` después de `auth` middleware (similar a `EnsurePasswordChanged`).

### Self-protection

- [x] **AC40**: WHEN un admin intenta desactivar su propia cuenta vía `users.toggle-active` THEN el backend retorna 422 con `errors.is_active = 'No puedes desactivar tu propia cuenta.'`.
- [x] **AC41**: WHEN un admin intenta vía `users.update` quitar todos sus roles administrativos AND es el último usuario con rol Admin (excluyendo super_admin) en el sistema THEN retorna 422 con `errors.roles = 'Eres el último administrador del sistema. No puedes quitarte el rol Admin.'`. La validación corre en `UserUpdateRequest::after()`.
- [x] **AC42**: WHEN un admin (no super_admin) intenta vía `users.update`, `users.destroy`, `users.toggle-active`, o `users.reset-password` modificar un usuario que tiene rol `super_admin` THEN retorna 403. Implementación: gate check en cada controller method o un policy `UserPolicy::ensureCanModify($actor, $target)`.

### Audit log

- [x] **AC43**: WHEN un admin actualiza los roles de un usuario THEN se escribe exactamente UNA entrada en `activity_log` con `subject_type=App\Models\User`, `subject_id=$user->id`, `causer_id=$admin->id`, `event=roles_synced`, `properties.old_roles=[...]`, `properties.new_roles=[...]`. Solo se escribe si los roles efectivamente cambiaron.
- [x] **AC44**: WHEN un admin actualiza permisos de un rol THEN se escribe exactamente UNA entrada con `subject_type=App\Models\Role` (modelo extendido local), `subject_id=$role->id`, `causer_id=$admin->id`, `event=permissions_synced`, `properties.added=[...keys]`, `properties.removed=[...keys]`. Solo se escribe si efectivamente cambiaron.
- [x] **AC45**: WHEN un admin actualiza la descripción de un rol THEN el cambio queda registrado vía `LogsActivity` trait sobre `App\Models\Role` con `properties.attributes.description` y `properties.old.description`.
- [x] **AC46**: WHEN un admin activa o desactiva un usuario vía `users.toggle-active` THEN el cambio queda registrado vía `LogsActivity` trait (la columna `is_active` debe estar en `getActivitylogOptions()->logOnly([...])` del modelo User).

### Authorization

- [x] **AC47**: WHEN un usuario sin permiso `VIEW_USERS` accede a `/users` THEN 403. Sin `VIEW_USERS` (que es prerequisito para ver la pantalla) accede a `/roles` o `/permissions` THEN 403. Sin `UPDATE_USERS` y haciendo POST `/users/{id}/active` THEN 403.
- [x] **AC48**: WHEN un super_admin accede a cualquiera de las rutas administrativas THEN 200 (bypass via `Gate::before` ya existente).
- [x] **AC49**: WHEN operator/driver/accounting acceden a `/users`, `/roles`, `/permissions` THEN 403 (no tienen `VIEW_USERS`).

### Code quality

- [x] **AC50**: WHEN se corre `npm run types` THEN no se introducen errores de TypeScript nuevos en los archivos creados/modificados.
- [x] **AC51**: WHEN se corre `vendor/bin/pint --dirty --format agent` THEN no quedan issues de formato.
- [x] **AC52**: WHEN se corre `php artisan enum:typescript` THEN se regeneran `resources/js/enums/Permission.ts`, `Role.ts`, **y nuevo** `PermissionGroup.ts`. Estos NO se editan a mano.

## Technical Specification

### Data Model

**Modificaciones (en migraciones primarias — early-dev rule):**

```
users (modify 0001_01_01_000000_create_users_table.php)
├── ...
├── is_active (boolean, NOT NULL, default true)
└── last_login_at (timestampTz, nullable)

roles (modify 2026_02_22_233832_create_permission_tables.php → spatie permission stub)
├── ...
└── description (text, nullable)
```

`User` model:
- Agregar `is_active` y `last_login_at` a `$fillable`.
- Agregar `is_active => boolean`, `last_login_at => datetime` al `casts()`.
- Actualizar `getActivitylogOptions()->logOnly([...])` para incluir `is_active` (y mantener `id`, `name`, `email`). NO incluir `last_login_at` — ese cambio es ruido y rotaría el audit log.

`Spatie\Permission\Models\Role` se extiende con un modelo local `App\Models\Role`:
- Trait `LogsActivity` con `getActivitylogOptions()->logOnly(['name','description'])`.
- Configurar en `config/permission.php` que el modelo de roles sea `App\Models\Role`.
- Agregar `description` a `$fillable`.

### Enums

**Nuevo enum `App\Enums\PermissionGroup`** (17 cases):

```php
enum PermissionGroup: string
{
    case DASHBOARD_SETTINGS = 'dashboard';
    case VEHICLES = 'vehicles';
    case DRIVERS = 'drivers';
    case THIRD_PARTIES = 'third-parties';
    case CONTRACTS = 'contracts';
    case SERVICES = 'services';
    case DAY_SUMMARY = 'day-summary';
    case INCIDENTS = 'incidents';
    case INVOICES = 'invoices';
    case REPORTS = 'reports';
    case FUEC = 'fuec';
    case GPS = 'gps';
    case USERS = 'users';
    case INCIDENT_TYPES = 'incident-types';
    case CATALOGS = 'catalogs';
    case AUDIT = 'audit';
    case DATA_IMPORTS = 'data-imports';
    case NOTIFICATIONS = 'notifications';

    public function label(): string { /* Spanish labels */ }
    public function order(): int { /* 0..17 stable sort key */ }
}
```

Nota: la cuenta es **18** (incluye GPS y DATA_IMPORTS además de los 17 del prototipo, porque el código actual los expone). Si el cliente decide ocultar GPS/Data Imports en algún momento, el feature flag/permission gate los oculta — el enum es la fuente de verdad estructural.

**Métodos nuevos en `App\Enums\Permission`:**

```php
public function group(): PermissionGroup
{
    return match ($this) {
        self::VIEW_DASHBOARD, self::VIEW_SETTINGS => PermissionGroup::DASHBOARD_SETTINGS,
        self::VIEW_VEHICLES, self::CREATE_VEHICLES, self::UPDATE_VEHICLES, self::DELETE_VEHICLES => PermissionGroup::VEHICLES,
        // ... resto
    };
}

/**
 * @return array<string, array{label: string, permissions: array<int, array{key: string, label: string}>}>
 */
public static function groupedForUi(): array
{
    $groups = [];
    foreach (PermissionGroup::cases() as $group) {
        $groups[$group->value] = ['label' => $group->label(), 'permissions' => []];
    }
    foreach (self::cases() as $perm) {
        $groups[$perm->group()->value]['permissions'][] = [
            'key' => $perm->value,
            'label' => $perm->label(),
            'description' => $perm->description(), // nuevo método
        ];
    }
    return $groups;
}

public function description(): string
{
    return match ($this) {
        self::VIEW_DASHBOARD => 'Acceso al panel principal con métricas en tiempo real.',
        // ... resto, basado en data.jsx del handoff
    };
}
```

`enum:typescript` regenera `resources/js/enums/PermissionGroup.ts` y agrega `PermissionGroupLabel` mirror.

### Routes

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/users` | `UserController@index` | `auth, verified, can:users.view` | `users.index` |
| POST | `/users` | `UserController@store` | `auth, verified, can:users.create` | `users.store` |
| PUT | `/users/{user}` | `UserController@update` | `auth, verified, can:users.update` | `users.update` |
| DELETE | `/users/{user}` | `UserController@destroy` | `auth, verified, can:users.delete` | `users.destroy` |
| PATCH | `/users/{user}/active` | `UserController@toggleActive` | `auth, verified, can:users.update` | `users.toggle-active` |
| POST | `/users/{user}/reset-password` | `UserController@resetPassword` | `auth, verified, can:users.update` | `users.reset-password` |
| GET | `/roles` | `RoleController@index` | `auth, verified, can:users.view` | `roles.index` |
| GET | `/roles/{role}` | `RoleController@show` | `auth, verified, can:users.view` | `roles.show` |
| PUT | `/roles/{role}` | `RoleController@update` | `auth, verified, can:users.update` | `roles.update` |
| GET | `/permissions` | `PermissionController@index` | `auth, verified, can:users.view` | `permissions.index` |

Notas:
- `Route::resource('users', ...)` se reemplaza por declaraciones explícitas que excluyen `create`, `edit`, `show` (todo via modal).
- `{role}` route binding: por `name` (no `id`). En `RouteServiceProvider` o en el controller method, `Route::bind('role', fn ($value) => SpatieRole::where('name', $value)->firstOrFail());`.
- Reuse `VIEW_USERS` para Roles + Permisos: conceptualmente la administración RBAC es un solo módulo. No agregar permisos nuevos como `VIEW_ROLES` / `MANAGE_ROLES`; simplifica el seeder.

### Permissions

**No se agregan permisos nuevos.** `VIEW_USERS`, `CREATE_USERS`, `UPDATE_USERS`, `DELETE_USERS` ya cubren todas las operaciones (incluyendo Roles y Permisos como pantallas read-only/edit del mismo dominio).

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Lista de usuarios | `resources/js/pages/users/index.tsx` | DataTable + filtros + modales (rewrite completo) |
| Lista de roles | `resources/js/pages/roles/index.tsx` | Grid de cards (nuevo) |
| Detalle de rol | `resources/js/pages/roles/show.tsx` | Info card + matriz de permisos + sticky save bar (nuevo) |
| Permisos | `resources/js/pages/permissions/index.tsx` | Referencia agrupada read-only (nuevo) |

| Component | Path | Description |
|-----------|------|-------------|
| `<UserDialog>` | `resources/js/components/admin/user-dialog.tsx` | Modal Create/Edit usuario |
| `<DeleteUserDialog>` | `resources/js/components/admin/delete-user-dialog.tsx` | AlertDialog confirmación delete |
| `<AdminTabs>` | `resources/js/components/admin/admin-tabs.tsx` | Tabs Usuarios/Roles/Permisos + badge Referencia |
| `<PermissionMatrix>` | `resources/js/components/admin/permission-matrix.tsx` | Lista de grupos colapsables con switches |
| `<SaveBar>` | `resources/js/components/admin/save-bar.tsx` | Sticky bar inferior con N cambios |
| `<UserAvatar>` | `resources/js/components/admin/user-avatar.tsx` | Avatar deterministic OKLCH + iniciales |
| `<UserRowActions>` | `resources/js/components/admin/user-row-actions.tsx` | DropdownMenu de fila |
| `<PasswordStrengthMeter>` | `resources/js/components/admin/password-strength-meter.tsx` | 4 segmentos con score 0-4 |

Pages a **eliminar** (rewrite forzado): `resources/js/pages/users/create.tsx`, `users/edit.tsx`, `users/show.tsx`. Wayfinder regenera y los actions correspondientes desaparecen.

### Migration Strategy

**modify-existing**. Estamos en desarrollo temprano y la memoria del proyecto incluye la regla [Edit primary migrations in early dev](memory: `feedback_edit_primary_migrations.md`). No hay datos de producción reales.

- `database/migrations/0001_01_01_000000_create_users_table.php`: agregar `is_active` y `last_login_at` al `Schema::create('users', ...)` original.
- `database/migrations/2026_02_22_233832_create_permission_tables.php`: agregar `$table->text('description')->nullable();` al `Schema::create($tableNames['roles'], ...)` (la tabla roles está en el mismo archivo de spatie).
- `database/migrations/2026_03_13_000000_seed_catalog_data.php`: actualizar `seedRolesAndPermissions()` para escribir descripciones por defecto en cada rol creado (firstOrCreate con `description` en el array de defaults).

Después: `./vendor/bin/sail artisan migrate:fresh --seed`.

### Login flow detail

**Listener**: `app/Listeners/UpdateLastLoginAt.php`

```php
class UpdateLastLoginAt
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }
}
```

`saveQuietly()` evita disparar `LogsActivity`.

Registrar en `AppServiceProvider::boot()`:
```php
Event::listen(Login::class, UpdateLastLoginAt::class);
```

**Inactive guard**: en `app/Providers/FortifyServiceProvider.php::boot()`:

```php
Fortify::authenticateUsing(function (Request $request) {
    $user = User::where('email', $request->email)->first();
    if (! $user || ! Hash::check($request->password, $user->password)) {
        return null;
    }
    if (! $user->is_active) {
        // Mensaje genérico para no filtrar existencia
        throw ValidationException::withMessages([
            Fortify::username() => 'Esta cuenta está desactivada. Contacta a un administrador.',
        ]);
    }
    return $user;
});
```

**Active middleware** (para sesiones ya autenticadas que se desactivan en otra sesión): `app/Http/Middleware/EnsureUserIsActive.php`:

```php
public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    if ($user && ! $user->is_active) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')
            ->withErrors(['email' => 'Esta cuenta está desactivada.']);
    }
    return $next($request);
}
```

Registrar en `bootstrap/app.php` `withMiddleware()` como middleware grupo `web`, después de `auth`. Asegurarse que se ejecuta DESPUÉS de auth pero ANTES de los controllers de feature.

## Tasks

### Backend

- [x] **B**: Migration — modificar `users` para agregar `is_active boolean default true` + `last_login_at timestampTz nullable`. Editar archivo primario `0001_01_01_000000_create_users_table.php`. Run `migrate:fresh --seed` y verificar.
- [x] **B**: Migration — modificar `roles` (de spatie/permission) para agregar `description text nullable`. Editar archivo primario `2026_02_22_233832_create_permission_tables.php` directamente.
- [x] **B**: Crear modelo `App\Models\Role` que extiende `Spatie\Permission\Models\Role` con `LogsActivity` trait, `description` en `$fillable`, `getActivitylogOptions()->logOnly(['name','description'])`. Configurar `config/permission.php` `models.role => App\Models\Role::class`.
- [x] **B**: Actualizar `User` model:
  - Agregar `is_active`, `last_login_at` a `$fillable`.
  - Agregar `is_active => boolean`, `last_login_at => datetime` al `casts()`.
  - Actualizar `getActivitylogOptions()->logOnly(['id','name','email','is_active'])` (sin `last_login_at`).
- [x] **B**: Crear enum `App\Enums\PermissionGroup` con 18 cases + `label()` (español) + `order()` (int 0..17 stable sort).
- [x] **B**: Agregar a `App\Enums\Permission`:
  - método `group(): PermissionGroup` con match exhaustivo.
  - método `description(): string` con copy en español (basado en `data.jsx` del handoff).
  - método estático `groupedForUi(): array` que retorna estructura `{groupKey => {label, permissions: [{key, label, description}]}}` ordenada por `PermissionGroup::order()`.
- [x] **B**: Correr `php artisan enum:typescript` y commitear el `PermissionGroup.ts` regenerado + actualizaciones a `Permission.ts`.
- [x] **B**: Actualizar seeder `2026_03_13_000000_seed_catalog_data.php`:
  - En `seedRolesAndPermissions()` agregar `description` a cada `firstOrCreate` de rol con copy del handoff (Super Admin: "Acceso total al sistema. Omite todas las verificaciones de permisos.", Admin: "Administra usuarios, configuración y catálogos de la plataforma.", etc.)
- [x] **B**: Crear `App\Listeners\UpdateLastLoginAt` que escucha `Login` y hace `saveQuietly` de `last_login_at = now()`. Registrar en `AppServiceProvider::boot()`.
- [x] **B0**: Crear middleware `App\Http\Middleware\EnsureUserIsActive`. Registrar en `bootstrap/app.php` en el grupo `web` después de `auth`.
- [x] **B1**: Override `FortifyServiceProvider::boot()` con `Fortify::authenticateUsing(...)` que valida `is_active` y throwea `ValidationException` con mensaje en español si está inactivo.
- [x] **B2**: Reescribir `UserStoreRequest`:
  - Cambiar `role` (string) por `roles` (array, `min:1`, cada item en `Rule::in([Role::ADMIN, OPERATOR, DRIVER, ACCOUNTING])`).
  - Agregar `is_active` (`required`, `boolean`).
  - Agregar `send_welcome_email` (`nullable`, `boolean`).
  - Mantener `name`, `email`, `password` con reglas actuales.
- [x] **B3**: Reescribir `UserUpdateRequest`:
  - Cambiar `role` por `roles[]` (igual que B12).
  - Agregar `is_active` requerido boolean.
  - **Quitar** `password` del request (la edición de password va por endpoint dedicado).
  - Agregar `after()` callback que valida self-protection AC41 (último admin) y AC42 (super_admin gating).
- [x] **B4**: Crear `UserToggleActiveRequest` (FormRequest) que:
  - `authorize()`: `Gate::allows(Permission::UPDATE_USERS->value)` + check no es super_admin (AC42).
  - `rules()`: vacío.
  - `after()`: rechaza si `$this->route('user')->id === $this->user()->id` con mensaje "No puedes desactivar tu propia cuenta." (solo si la operación va a desactivar — verificar `is_active` actual).
- [x] **B5**: Crear `UserResetPasswordRequest` (FormRequest) que:
  - `authorize()`: igual a B14.
  - `rules()`: vacío.
- [x] **B6**: Reescribir `App\Http\Controllers\UserController`:
  - **Eliminar** métodos `create`, `edit`, `show`.
  - `index(Request $request)`: integrar `useServerTable` style — paginate + AllowedFilters (`AllowedFilter::callback('search', ...)`, `AllowedFilter::callback('roles', ...)` que matchea any role, `AllowedFilter::exact('is_active')`), AllowedSorts.
  - `store(UserStoreRequest $request)`: `User::create([...])`, `syncRoles($validated['roles'])`, dispatch `WelcomeUserNotification` si `send_welcome_email=true` (con password regenerado por `Str::password(16)` y `must_change_password=true`).
  - `update(UserUpdateRequest $request, User $user)`: detect role diff, persist, write `activity('roles_synced')` solo si cambió.
  - `destroy(...)`: ya existe + agregar check "último admin" (delegar a `User::isLastAdmin()` helper).
  - `toggleActive(UserToggleActiveRequest $request, User $user)`: invierte `is_active`, persist, retorna redirect back con flash.
  - `resetPassword(UserResetPasswordRequest $request, User $user)`: `Str::password(16)`, `forceFill(['password' => Hash::make($pwd), 'must_change_password' => true])->save()`, disparar email Fortify forgot-password (`Password::sendResetLink(['email' => $user->email])` o un `Notification` custom).
  - Helper privado `availableRoles()`: ya existe — sigue excluyendo super_admin.
- [x] **B7**: Crear `App\Http\Controllers\RoleController`:
  - `index()`: lista 5 roles del enum desde DB (con `withCount('users')` y `withCount('permissions')`), retorna Inertia 'roles/index' con `roles: [{id, name, label, description, users_count, permissions_count, locked: name === 'super_admin'}]`.
  - `show(SpatieRole $role)`: route binding por `name`. Carga rol con `permissions:id,name`, usuarios (top 6 + count). Retorna Inertia 'roles/show' con `role`, `users` (ids/names para avatares), `permissionGroups` (vía `Permission::groupedForUi()`), `assignedPermissions` (string[] de keys actuales), `locked` (bool).
  - `update(RoleUpdateRequest $request, SpatieRole $role)`: persiste `description` + `syncPermissions($validated['permissions'])`. Audit log diff. Si `role->name === 'super_admin'` retorna 403 (no se puede tocar).
- [x] **B8**: Crear `App\Http\Requests\RoleUpdateRequest`:
  - `authorize()`: `Gate::allows(Permission::UPDATE_USERS->value)` + check rol no es super_admin.
  - `rules()`: `description` nullable string max:500, `permissions` array, cada item en `Rule::in(array_map(fn (Permission $p) => $p->value, Permission::cases()))`.
- [x] **B9**: Crear `App\Http\Controllers\PermissionController` con un solo método `index()` que retorna Inertia 'permissions/index' con `groups: Permission::groupedForUi()`.
- [x] **B0**: Crear `App\Notifications\WelcomeUserNotification` que extiende `Notification` con email markdown que incluye: nombre, link a `/forgot-password`, y nota de que el primer login pedirá cambiar password. Subject: "Bienvenido a SGTE".
- [x] **B1**: Helper `User::isLastAdmin(): bool` — `static::role(Role::ADMIN->value)->count() === 1 && $this->hasRole(Role::ADMIN->value)`. Usado en self-protection.
- [x] **B2**: Definir route bindings + rutas en `routes/web.php`:
  - `Route::bind('role', fn ($value) => SpatieRole::where('name', $value)->firstOrFail());`
  - Sustituir `Route::resource('users', ...)` por declaraciones explícitas (sin create/edit/show).
  - Agregar las 6 rutas nuevas (toggle-active, reset-password, roles index/show/update, permissions.index).
  - Reusar `can:` middleware con permisos existentes.

### Frontend

- [x] **F**: Eliminar `resources/js/pages/users/create.tsx`, `users/edit.tsx`, `users/show.tsx`. Wayfinder regenera y desaparecen los actions correspondientes.
- [x] **F**: Reescribir `resources/js/pages/users/index.tsx` siguiendo el patrón de los CRUDs rebuilt (referencia: `resources/js/pages/services/index.tsx`):
  - `<DataTable>` + `useServerTable`.
  - Header con h1 "Usuarios", subtítulo, primary button "Nuevo usuario".
  - `<AdminTabs current="users" />`.
  - Toolbar: search input, role multi-select combobox, status popover, "Limpiar filtros".
  - Columnas exactas según AC5.
  - Empty states según AC10.
  - State: `dialogOpen`, `dialogMode`, `selectedUser` para el modal.
- [x] **F**: Crear `resources/js/components/admin/user-avatar.tsx`. Implementa hue determinista por id `[40,180,260,140,20,300,220,90][id % 8]` → `oklch(0.75 0.09 ${hue})`. Iniciales = primeras 2 palabras del nombre, primera letra de cada uno, uppercase.
- [x] **F**: Crear `resources/js/components/admin/admin-tabs.tsx`. Recibe `current: 'users' | 'roles' | 'permissions'`. Renderiza `<Tabs value={current}>` con tres `<TabsList>` items + Badge muted "Referencia" a la derecha. Click navega vía `<Link>` Inertia.
- [x] **F**: Crear `resources/js/components/admin/user-row-actions.tsx`. DropdownMenu con 4 items + separator + delete. Recibe callbacks `onEdit`, `onResetPassword`, `onToggleActive`, `onDelete`.
- [x] **F**: Crear `resources/js/components/admin/password-strength-meter.tsx`. 4 segmentos divs. Score 0-4 según AC17. Etiqueta "Débil/Aceptable/Buena/Fuerte" debajo.
- [x] **F**: Crear `resources/js/components/admin/user-dialog.tsx` (Modal A). Acepta `mode: 'create' | 'edit'` + `user?` (en edit). Form con `useForm` Inertia. Roles combobox custom (input-styled box con chips + popover con search + checkbox list). Validación client-side AC16. En create: include password fields + meter. En edit: omitirlos.
- [x] **F**: Crear `resources/js/components/admin/delete-user-dialog.tsx` (Modal B). AlertDialog con copy AC18. Submit hace DELETE via `<Form>`.
- [x] **F**: Crear `resources/js/pages/roles/index.tsx`. Header + AdminTabs + grid de 5 cards según AC20-22. Cada card link a `/roles/{role}`. Super Admin con Lock tooltip + Editar disabled.
- [x] **F0**: Crear `resources/js/components/admin/permission-matrix.tsx`. Recibe `groups: PermissionGroup[]` (con label + permissions[]) + `assigned: Set<string>` + `onChange: (newSet) => void` + `locked: bool`. Renderiza grupos colapsables con header (chevron + label + badge on/all + Marcar todo/Desmarcar todo) y rows con label + desc + Switch.
- [x] **F1**: Crear `resources/js/components/admin/save-bar.tsx`. Sticky bar inferior absoluta. Props: `dirty: bool`, `addedCount`, `removedCount`, `onDiscard`, `onSave`. Animación enter 180ms (CSS transform translate-y).
- [x] **F2**: Crear `resources/js/pages/roles/show.tsx`. Grid 340/1fr según AC23. Columna izquierda: 2 cards (Información del rol con descripción inline-edit + Usuarios con este rol con avatares apilados). Columna derecha: card Permisos con `<PermissionMatrix />`. Sticky save bar.
- [x] **F3**: Crear `resources/js/pages/permissions/index.tsx`. Header + AdminTabs + Alert read-only + grid de cards por grupo según AC31-33.
- [x] **F4**: Modificar `resources/js/components/app-sidebar.tsx`. Agregar items "Roles" (`Shield` icon, `rolesIndex()`) y "Permisos" (`KeyRound`, `permissionsIndex()`) al grupo "Administración" entre "Usuarios" y "Auditoría". Mantener "Importaciones" al final. Verificar gates con `Permission.VIEW_USERS`.
- [x] **F5**: Si los CRUDs existentes no exponen un combobox multi-select reusable, crear `resources/js/components/ui/multi-combobox.tsx` (popover con search + checkbox list + chips de selección). Documentar minimal API.

### Tests

- [x] **T** (Pest): `tests/Feature/Http/Controllers/UserControllerTest.php` — actualizar / agregar:
  - admin can view index with paginated users + correct roles serialization.
  - admin can store user with multiple roles + welcome email dispatched when flag set + ProcessQueueDispatched assertion.
  - admin cannot store user with `super_admin` in roles[].
  - admin cannot store with empty roles[].
  - admin can update user incl. roles diff → activity log written exactly once with old/new roles.
  - admin can update user without role change → no activity_log row written.
  - admin cannot update super_admin user (403).
  - admin cannot remove last admin (422).
  - admin can toggle active.
  - admin cannot deactivate self (422).
  - admin cannot toggle super_admin (403).
  - admin can reset password → password regenerated + must_change_password=true + email queued.
  - admin cannot reset super_admin password.
  - filters: search, roles (multi), is_active.
  - non-admin gets 403 on every endpoint.
- [x] **T** (Pest): `tests/Feature/Http/Controllers/RoleControllerTest.php`:
  - admin can view index — 5 roles with users_count + permissions_count.
  - admin can view show for any non-super_admin role.
  - admin can view super_admin show (locked render path).
  - admin can update description + permissions → 1 activity_log row with added/removed.
  - admin cannot update super_admin role (403).
  - admin update with no changes → no activity_log row.
  - non-admin gets 403.
- [x] **T** (Pest): `tests/Feature/Http/Controllers/PermissionControllerTest.php`:
  - admin can view index — payload contains 17 groups (DASHBOARD_SETTINGS, VEHICLES, ..., NOTIFICATIONS) + GPS + DATA_IMPORTS = 18.
  - non-admin gets 403.
- [x] **T** (Pest): `tests/Feature/Auth/InactiveUserCannotLoginTest.php`:
  - given a user with `is_active=false` + correct password → login fails with `'Esta cuenta está desactivada. Contacta a un administrador.'`.
  - given the same user with wrong password → standard "These credentials do not match" (no info leak).
  - active user logs in successfully + `last_login_at` is updated.
- [x] **T** (Pest): `tests/Feature/Middleware/EnsureUserIsActiveTest.php`:
  - logged-in user gets deactivated mid-session → next request redirects to /login with error.
- [x] **T** (Pest): `tests/Feature/Listeners/UpdateLastLoginAtTest.php`:
  - dispatching Login event for a user updates `last_login_at` without dispatching a LogsActivity entry (saveQuietly).
- [x] **T** (Dusk): `tests/Browser/AdminUsersIndexTest.php`:
  - admin login → /users.
  - assert page renders without errors.
  - assert key headings/labels: "Usuarios", "Nuevo usuario", "Buscar por nombre o correo...", "Rol", "Todos los estados".
  - assert tabs visible: "Usuarios" (active), "Roles", "Permisos", "Referencia" badge.
  - assert table headers: "Nombre", "Correo", "Roles", "Último acceso", "Estado", "Acciones".
  - filter by role + assert filtered rows.
  - assert at least one row with multiple role badges.
  - screenshot at key steps.
- [x] **T** (Dusk): `tests/Browser/AdminUserCreateModalTest.php`:
  - click "Nuevo usuario" → modal opens.
  - assert modal title "Nuevo usuario", subtitle, fields visible.
  - fill name, email, password (mismatch) → assert "Guardar usuario" disabled.
  - match passwords + select 1+ role → button enables.
  - submit → modal closes, table contains new row.
  - screenshot.
- [x] **T** (Dusk): `tests/Browser/AdminRoleEditTest.php`:
  - admin → /roles → click "Editar" en card Administrador → /roles/admin.
  - assert page renders with sections "Información del rol", "Usuarios con este rol", "Permisos".
  - toggle off a permission → assert sticky save bar appears with "1 cambio sin guardar".
  - click "Guardar cambios" → assert save bar disappears, toast/success.
  - reload → assert toggled permission persisted.
  - screenshot.
- [x] **T0** (Dusk): `tests/Browser/AdminRoleSuperAdminLockedTest.php`:
  - admin → /roles → assert Super Admin card has Lock badge + "Editar" disabled.
  - direct nav `/roles/super_admin` → assert all switches disabled, no save bar.
  - screenshot.
- [x] **T1** (Dusk): `tests/Browser/AdminPermissionsReferenceTest.php`:
  - admin → /permissions.
  - assert read-only Alert "Solo lectura. Los permisos son definidos por la plataforma..." with link to /roles.
  - assert at least 17 group cards rendered with permission keys in monospace.
- [x] **T2** (Dusk): `tests/Browser/AdminUserDeactivationTest.php`:
  - admin → /users → toggle Estado switch on a non-self row → row dims (opacity 0.72) + Badge "Inactivo".
  - assert PATCH happened (no JS error, network tab via browser-logs).
  - logout, login as the deactivated user → assert error "Esta cuenta está desactivada...".

### Verification & polish

- [x] **V**: Correr `./vendor/bin/sail test --compact` — full suite verde (incluyendo tests existentes que dependían del shape antiguo de UserStoreRequest single-role; deben actualizarse en B12-B13 si rompen).
- [x] **V**: Correr `./vendor/bin/sail dusk` localmente — todos los nuevos tests pasan.
- [x] **V**: Correr `./vendor/bin/sail npm run types` — sin errores nuevos.
- [x] **V**: Correr `./vendor/bin/sail vendor/bin/pint --dirty --format agent` — sin issues.
- [x] **V**: Playwright MCP smoke walkthrough con `admin@sgte.app` (password `password`):
  - 1. /users: ver tabla con 4-5 usuarios seed + filtro por rol "Administrador" reduce el set.
  - 2. /users → "Nuevo usuario": llenar form con 2 roles, submit, ver fila nueva.
  - 3. /users → toggle Estado en una fila → fila se atenúa.
  - 4. /roles: ver 5 cards, Super Admin locked.
  - 5. /roles/admin: cambiar descripción + togglear "Eliminar facturas" → save bar aparece → guardar.
  - 6. /permissions: ver 17+ cards agrupados, link a /roles funciona.
- [x] **V**: Actualizar `CLAUDE.md` línea con el conteo de permisos si procede (cuenta sigue 53 — no cambia).
- [x] **V**: Verificar que el bloqueo de login para usuarios inactivos NO leak info: el mensaje es genérico aunque el password sea correcto. Confirmar manualmente.

## Verification

### 1. Interactive verification — Playwright MCP

Reference users (todos password `password`, super admin via `SUPER_ADMIN_USER` / `SUPER_ADMIN_PASSWORD`):

| Role | Email |
|---|---|
| Admin | `admin@sgte.app` |
| Operator | `operator@sgte.app` |
| Driver | `driver@sgte.app` |
| Accounting | `accounting@sgte.app` |

Escenarios MCP (con `mcp__playwright__browser_snapshot` para a11y):

- [x] **MCP1**: Login como admin → `/users` renderiza tabla con paginación, columnas correctas, badges multi-rol visibles.
- [x] **MCP2**: Filtrar `?filter[roles]=admin&filter[is_active]=true` → solo admins activos.
- [x] **MCP3**: Click "Nuevo usuario" → modal abre con todos los campos, password meter responde.
- [x] **MCP4**: Crear usuario con 2 roles + `send_welcome_email=true` → fila nueva visible, log de mailpit muestra email enviado (`http://localhost:8025`).
- [x] **MCP5**: `/roles` → 5 cards renderizadas, Super Admin con Lock tooltip.
- [x] **MCP6**: `/roles/admin` → toggle un permiso → save bar slides in, "1 cambio sin guardar". Guardar → bar desaparece, recarga persiste.
- [x] **MCP7**: `/permissions` → 17+ cards agrupados, Alert read-only visible, link "Roles" navega correctamente.
- [x] **MCP8**: Login como operator → `/users` → 403. Sidebar no muestra grupo Administración.
- [x] **MCP9**: Toggle Estado de un usuario → fila se atenúa optimísticamente. Logout → login como ese usuario → error de cuenta desactivada.

### 2. Backend regression — Pest feature tests

Cubierto por T1-T6 arriba. Run con `./vendor/bin/sail test --compact tests/Feature/Http/Controllers/UserControllerTest.php` (y demás).

### 3. UI regression — Laravel Dusk browser tests

Cubierto por T7-T12 arriba. Run con `./vendor/bin/sail dusk`. Cada test:

- assert no error banners / exception traces visibles.
- assert headings/labels en español según handoff.
- assert layout correcto (tabs + columns + tabla).
- screenshot en pasos clave.

Cuando se necesite DB limpia: agregar `php artisan migrate:fresh --seed --no-interaction` al setup del test.

### 4. API endpoints — curl

Sin endpoints API públicos en este requerimiento (todo Inertia). Sin curl tests.

## Dependencies

- `spatie/laravel-permission` (ya instalado).
- `spatie/laravel-activitylog` (ya instalado).
- `laravel/fortify` (ya instalado, customización via `authenticateUsing`).
- shadcn primitives requeridos según handoff: `Button, Input, Label, Select, Checkbox, Switch, Badge, Avatar, Card, Table, Tabs, Dialog, AlertDialog, DropdownMenu, Tooltip, Popover, Command, Separator, Alert`. Si alguno falta, `npx shadcn@latest add <name>` (verificar antes de empezar el frontend).

No depende de otros requirements pendientes.

## Notes

- **Memoria del proyecto**: la regla [Edit primary migrations in early dev](feedback_edit_primary_migrations.md) aplica — modificamos `0001_01_01_000000_create_users_table.php` y `2026_02_22_233832_create_permission_tables.php` directamente sin migraciones de backfill.
- **Memoria del proyecto**: [Authorization in FormRequest::authorize()](feedback_authorization_in_form_requests.md) — todas las FormRequests nuevas hacen `Gate::allows(...)` en `authorize()`, no en el controller body.
- **Memoria del proyecto**: [Run Dusk tests at feature-end](feedback_run_dusk_at_feature_end.md) — Dusk es obligatorio y debe correrse localmente (`./vendor/bin/sail dusk`) antes de marcar la feature como done.
- **Memoria del proyecto**: [Driver role isolation](project_driver_role_model.md) — Driver mantiene sus 6 permisos puntuales sin tocar; el grupo Administración del sidebar permanece oculto para él.
- **Handoff source**: `docs/_drafts/rbac_control_design_handoff_sgte_admin/` (README.md + 6 PNGs + prototype/ src). Las claves de permisos del prototipo (`vehicles.edit`, `third.view`, `nov.create`, `services.edit.projected`) se traducen mentalmente a las del enum existente; el handoff es guía visual, no fuente de IDs.
- **Avatar OKLCH**: `oklch(0.75 0.09 <hue>)` con paleta de 8 hues `[40, 180, 260, 140, 20, 300, 220, 90]` indexada por `id % 8`. Tipografía blanca encima.
- **shadcn/ui style**: el proyecto ya usa shadcn (revisar `components.json`); seguir conventions existentes. NO instalar nuevas families de iconos — solo `lucide-react`.
- **Wayfinder**: tras agregar las rutas, regenera automáticamente `resources/js/actions/` y `resources/js/routes/` en build. No editar manualmente.
- **Sidebar grupo "Administración"**: el `permission` del grupo en `app-sidebar.tsx` ya es `Permission.VIEW_USERS` — no cambia. Si en el futuro se separa un permiso `MANAGE_ROLES` distinto, refactorizar entonces.
- **Welcome email**: el email markdown debe incluir un link a `/forgot-password` (nombre de ruta `password.request` de Fortify) para que el usuario fije su propio password. El password aleatorio que genera `Str::password(16)` solo existe transiente — el primer login hace `EnsurePasswordChanged` middleware redirigir a `/settings/password`.
