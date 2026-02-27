# ADR-001: Sistema de permisos en el frontend

**Estado:** Aceptado
**Fecha:** 2026-02-26

## Contexto

La aplicacion necesita mostrar u ocultar elementos de UI (items de navegacion, botones, secciones) segun los permisos del usuario autenticado. Los permisos se gestionan en el backend con `spatie/laravel-permission` y estan definidos como enums PHP en `App\Enums\Permission` y `App\Enums\Role`.

El frontend React (via Inertia.js) necesita:
1. Acceso a los enums de permisos con type safety.
2. Conocer los permisos y roles del usuario actual.
3. Una utilidad para evaluar permisos, respetando el bypass de super_admin.

## Decision

### 1. Generacion de enums TypeScript desde PHP

Se creo el comando `php artisan enum:typescript` que escanea los enums string-backed en `app/Enums/` y genera archivos `.ts` en `resources/js/enums/` con:
- Objeto `const` (para usar como constantes)
- `type` union (para type safety)
- Mapa de labels (si el enum tiene metodo `label()`)

Los archivos generados se versionan en git (no en `.gitignore`) porque el comando es manual y un dev que clone el repo necesita que compilen sin pasos extra. Se regeneran con `php artisan enum:typescript` cuando cambian los enums PHP.

### 2. Compartir permisos via Inertia

El middleware `HandleInertiaRequests` comparte `auth.permissions` (array de strings) y `auth.roles` (array de strings) en cada respuesta. Esto evita requests adicionales y funciona tanto en SSR como en el browser.

Para super_admin, los permisos llegan como array vacio (no tiene permisos directos asignados); el bypass se maneja en el frontend.

### 3. Utilidades frontend

- **`usePermissions()` hook** — Expone `can(permission)`, `hasRole(role)`, `isSuperAdmin`. Si el usuario tiene rol `super_admin`, `can()` siempre retorna `true` (espejo de `Gate::before()` en el backend).
- **`<Can permission={...}>` componente** — Renderizado condicional declarativo con prop `fallback` opcional.
- **`NavItem.permission`** — Campo opcional en el tipo `NavItem`; los componentes de navegacion filtran automaticamente items sin permiso.

### 4. Doble capa de seguridad

El frontend es solo UX (ocultar lo que no aplica). La autorizacion real se mantiene en el backend via middleware `can:` en rutas y policies en controladores.

## Consecuencias

**Positivas:**
- Type safety end-to-end: los mismos valores de permisos en PHP y TypeScript.
- Agregar un permiso a un nav item es declarativo (una propiedad).
- El bypass de super_admin se replica fielmente en el frontend.

**Negativas:**
- Requiere ejecutar `php artisan enum:typescript` manualmente cuando cambian enums PHP.
- Los permisos se envian en cada respuesta Inertia (peso minimo, ~1KB para 43 permisos).

**Archivos clave:**
- `app/Console/Commands/GenerateTypescriptEnums.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/hooks/use-permissions.ts`
- `resources/js/components/can.tsx`
- `resources/js/enums/` (generado)
