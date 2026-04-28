---
name: admin-data-imports
type: feat
scope: admin-data-imports
status: completed
priority: high
created_date: 2026-04-27
completed_date: 2026-04-27
srs_refs: []
migration_strategy: modify-existing
---

# Admin Data Imports — carga masiva por CSV/XLSX

## Description

Construir una funcionalidad de **importación masiva de datos** disponible para Super Admin desde la UI web. El usuario sube un archivo CSV o XLSX (≤ 20 MB) con un tipo de entidad determinado (usuarios, terceros, conductores, vehículos), el archivo se persiste en el disco S3 (MinIO en stg/prod), un job de Horizon en cola dedicada lo procesa en background, y la UI muestra progreso en vivo + resumen final + descarga de errores si los hubo.

La funcionalidad reemplaza la carga manual desde Dokploy Terminal (seeders ad-hoc, Tinker) y la creación uno-a-uno desde la UI cuando el cliente entrega un listado en Excel para go-live de un nuevo entorno.

El alcance v1 cubre 4 entidades: **Usuarios, Terceros, Conductores, Vehículos**. Soporta dos modos: **`dry_run`** (validar sin persistir) y **`update_existing`** (actualizar filas con clave existente). Las plantillas se sirven estáticamente desde `database/csv/templates/` y los catálogos de referencia (EPS, fondos, municipios, departamentos, tipos de documento, tipos de novedad, tipos de incidente) se exportan dinámicamente como CSV. El histórico de imports se conserva indefinidamente; los archivos en MinIO se purgan a los 90 días por un comando programado, además de un botón de purga manual.

Para la plantilla `users.csv`, la columna `password` es **opcional**: si viene vacía el sistema autogenera una contraseña, marca `users.must_change_password = true` y un middleware web redirige al usuario a `/password/change` en su primer login hasta que actualice la contraseña.

**Out of scope (candidatos a v2):**

- XLSX con dropdowns pre-llenados desde catálogos (servimos plantillas CSV planas).
- Importación de modelos transaccionales (contratos, servicios, facturas, novedades).
- "Reintentar fila" individual sobre filas erradas (se corrige el archivo y se sube de nuevo).
- Comando CLI artisan equivalente (descartado a propósito; flujo 100% UI).
- Bootstrap de Super Admin (se asume que ya existe vía catalog migration).
- Importación desde URL pública o S3 externo.
- Programación recurrente / cron interno.
- Detección de columnas modificadas para imports incrementales.
- Email de bienvenida con link de reset (se prefiere el flujo de redirect en el primer login).

## Acceptance Criteria

- [x] **AC1**: WHEN un Super Admin navega a `/admin/imports` THEN ve una página con: card "Plantillas" con 4 botones de descarga (Usuarios, Terceros, Conductores, Vehículos); card "Catálogos de referencia" con 7 links (EPS, Fondos de Pensiones, Fondos de Cesantías, Municipios, Departamentos, Tipos de Documento, Tipos de Novedad); banner persistente "Los archivos se eliminan automáticamente 90 días después de completarse. El histórico se conserva indefinidamente."; botón "Nueva carga"; tabla paginada del histórico de imports con columnas `Fecha · Tipo · Archivo · Estado · Resumen (+N ~M ⊘P ✗Q) · Usuario · Acciones`.
- [x] **AC2**: WHEN un usuario sin permiso `MANAGE_DATA_IMPORTS` (admin, operator, driver, accounting, invitado) navega a cualquier ruta bajo `/admin/imports/*` THEN recibe 403 (autenticado) o 401 / redirect a login (no autenticado). El sidebar item "Importaciones" NO aparece para esos roles.
- [x] **AC3**: WHEN el Super Admin descarga `GET /admin/imports/templates/{users|third-parties|drivers|vehicles}` THEN recibe el CSV correspondiente desde `database/csv/templates/{filename}.csv` con `Content-Type: text/csv; charset=UTF-8` y nombre `plantilla_{filename}.csv`. WHEN el slug es inválido THEN 404.
- [x] **AC4**: WHEN el Super Admin descarga `GET /admin/imports/reference/{eps|pension-funds|severance-funds|municipalities|departments|document-types|incident-types}` THEN recibe un CSV streamed desde la DB ordenado por `code` ASC con headers según el catálogo (ver §Templates en spec). WHEN el slug es inválido THEN 404.
- [x] **AC5**: WHEN el Super Admin envía `POST /admin/imports` con `type`, `csv` (file), `dry_run`, `update_existing` válidos THEN `DataImportStoreRequest` valida `mimes:csv,txt,xlsx`, `max:20480` KB; el archivo se guarda en `s3://imports/{type}/{ulid}.{ext}`; se crea fila `data_imports` con `status=queued`, `dry_run`, `update_existing` reflejando el form; `ProcessDataImportJob::dispatch($import)`; redirige a `/admin/imports/{id}` con flash `success`.
- [x] **AC6**: WHEN el archivo enviado supera 20 MB THEN el frontend pre-bloquea con mensaje "El archivo excede el límite de 20 MB"; si igualmente llega al backend (curl), `DataImportStoreRequest` retorna 422 con `csv: 'El archivo excede el límite de 20 MB.'`.
- [x] **AC7**: WHEN el archivo no es CSV ni XLSX (extensión + mime) THEN 422 con `csv: 'Solo se aceptan archivos CSV o XLSX.'`.
- [x] **AC8**: WHEN `ProcessDataImportJob` ejecuta sobre un archivo válido THEN actualiza `status = processing`, escribe `started_at`, valida el header contra `expectedHeaders()` del importer concreto, cuenta filas (CSV vía `substr_count`, XLSX vía iteración con `simple-excel`), guarda `rows_total`, procesa en chunks transaccionales de 200 filas, actualiza counters cada 200 filas (`rows_processed`, `rows_created`, `rows_updated`, `rows_skipped`, `rows_errored`), al terminar marca `status = completed` y `completed_at`.
- [x] **AC9**: WHEN una fila falla validación (Capa 2: `Validator::make`), o tiene una FK rota en `transformRow`, o duplica una clave natural ya vista en el batch (Capa 4: `seenKeys`) THEN la fila se cuenta en `rows_errored++`, se escribe a `errors.csv` con columnas `row_number, error_message, original_data` (JSON UTF-8 con la fila cruda) y el procesamiento **continúa**. Al final, si `rows_errored > 0`, `errors.csv` se sube a `s3://imports/{type}/{ulid}_errors.csv` y `data_imports.errors_path` queda poblado.
- [x] **AC10**: WHEN el header del archivo difiere del header esperado (faltan columnas) THEN el job marca `status = failed`, `error_message = "Faltan columnas en el archivo: [a, b]. Descargue la plantilla actualizada."` y NO procesa ninguna fila. Columnas extra son aceptadas (warning en log).
- [x] **AC11**: WHEN una fila válida tiene una clave natural que YA existe en la DB AND `import.update_existing = false` THEN `rows_skipped++` y NO se modifica el registro. WHEN existe AND `update_existing = true` THEN `applyUpdate()` se ejecuta y `rows_updated++`. WHEN no existe THEN `persistNew()` se ejecuta y `rows_created++`.
- [x] **AC12**: WHEN `import.dry_run = true` THEN el job recorre, valida, resuelve FKs, clasifica cada fila exactamente igual que en modo real, **pero NO llama a `persistNew()` ni `applyUpdate()`**. Los counters reflejan lo que **hubiera pasado**. Después del job, `Driver::count()`, `User::count()`, `ThirdParty::count()`, `Vehicle::count()` (según `type`) NO cambian.
- [x] **AC13**: WHEN una fila en `users.csv` tiene `password` vacío THEN se autogenera (`Str::password(16)`), se guarda hasheada (`bcrypt`), y el usuario queda con `must_change_password = true`. WHEN tiene `password` explícito THEN se hashea y `must_change_password = false`. La columna `users.must_change_password` (boolean, default `false`) se agrega editando la migración primaria `database/migrations/0001_01_01_000000_create_users_table.php` (no backfill: edición directa per `feedback_edit_primary_migrations`).
- [x] **AC14**: WHEN un usuario autenticado tiene `must_change_password = true` AND la request NO es para `/password/change`, `/logout`, `/settings/password`, ni rutas de assets/Inertia partials THEN un middleware `EnsurePasswordChanged` redirige a `/password/change`. WHEN actualiza la contraseña THEN el flag se setea a `false` y el flujo normal continúa. El middleware se registra en `bootstrap/app.php` dentro de `withMiddleware()->web(append: [...])`.
- [x] **AC15**: WHEN el Super Admin abre `/admin/imports/{id}` con un import en estado terminal (`completed` o `failed`) THEN `usePoll(2000, ...)` está apagado (`onlyWhen: !isTerminal`). En estado `queued` o `processing` el polling actualiza solo el prop `import` cada 2 segundos.
- [x] **AC16**: WHEN un import dry-run termina en `completed` AND `hasFiles()` THEN se muestra botón "Reintentar como import real". WHEN se hace click THEN se envía `POST /admin/imports` con `from_import_id={dry_run_id}` (sin `csv` file); el controller carga el `DataImport` origen, copia `path`, `original_filename`, `type`, `update_existing`, crea **nueva** fila `data_imports` con `dry_run=false`, dispara el job, y redirige a la nueva página show. La fila origen se preserva (audit linaje).
- [x] **AC17**: WHEN `ProcessDataImportJob` lanza una excepción no recuperable THEN el método `failed(Throwable $e)` actualiza `status = failed`, `error_message = substr($e->getMessage(), 0, 1000)`, `completed_at = now()` y loggea `import_id` + exception (sin contenido de filas).
- [x] **AC18**: WHEN `imports:reap-stuck` corre (cron `*/5 * * * *`) THEN actualiza a `status = failed` toda fila con `status = processing` AND `started_at < now() - 35 minutes`, con `error_message = 'Job interrumpido (timeout o caída del worker)'` y `completed_at = now()`.
- [x] **AC19**: WHEN `imports:purge-old-files` corre (cron diario a las 03:00) THEN para cada `DataImport` con `path != null` AND `files_purged_at IS NULL` AND `completed_at < now() - 90 days`: borra `path` y `errors_path` del disco; actualiza la fila con `path = null`, `errors_path = null`, `files_purged_at = now()`. La fila `data_imports` se mantiene.
- [x] **AC20**: WHEN el Super Admin presiona "Eliminar archivos" en `/admin/imports/{id}` THEN se envía `DELETE /admin/imports/{id}/files`; los archivos se borran de S3; la fila se actualiza igual que en la purga automática. La UI muestra banner "Los archivos fueron eliminados".
- [x] **AC21**: WHEN el Super Admin presiona "Descargar archivo original" o "Descargar errores" AND `hasFiles()` THEN `Storage::disk('s3')->download(...)` retorna el archivo con su nombre original (o `errores_{id}.csv`). WHEN `files_purged_at != null` THEN 410 con mensaje "Archivo no disponible (purgado)". WHEN `errors_path = null` THEN 404 al pedir errores.
- [x] **AC22**: WHEN se construye la imagen Docker de producción THEN `docker/production/php-uploads.ini` se copia a `/usr/local/etc/php/conf.d/php-uploads.ini` con `upload_max_filesize=25M`, `post_max_size=30M`, `memory_limit=256M`. Verificable con `docker run --entrypoint php sgte-app:latest -i | grep -E '^(upload_max_filesize|post_max_size|memory_limit)'`.
- [x] **AC23**: WHEN se ejecuta `php artisan horizon` THEN existe el supervisor `supervisor-imports` con `queue: ['imports']`, `maxProcesses: 1`, `tries: 1`, `timeout: 1800`, `memory: 256` en los entornos `production`, `staging`, `local`. La cola por defecto del job es `imports` (declarada como `public string $queue = 'imports'`).
- [x] **AC24**: WHEN `npm run types`, `./vendor/bin/sail npm run lint` y `./vendor/bin/sail pint --test --format agent` se ejecutan THEN no hay nuevos errores. WHEN `./vendor/bin/sail test --compact` corre THEN la suite (incluyendo los nuevos tests Pest) pasa. WHEN `./vendor/bin/sail dusk --filter=AdminDataImports` corre THEN los Dusk tests pasan localmente.

## Technical Specification

### Data Model

#### Nueva tabla `data_imports`

```
data_imports
├── id (bigint, PK)
├── user_id (bigint, FK → users.id, ON DELETE CASCADE)
├── type (varchar(32))                  # users | third_parties | drivers | vehicles
├── original_filename (varchar)
├── disk (varchar(32), default='s3')
├── path (varchar, nullable)            # nullable cuando se purga
├── errors_path (varchar, nullable)
├── status (varchar(16), default='queued')   # queued | processing | completed | failed
├── dry_run (boolean, default=false)
├── update_existing (boolean, default=false)
├── rows_total (int unsigned, nullable)
├── rows_processed (int unsigned, default=0)
├── rows_created (int unsigned, default=0)
├── rows_updated (int unsigned, default=0)
├── rows_skipped (int unsigned, default=0)
├── rows_errored (int unsigned, default=0)
├── error_message (text, nullable)
├── started_at (timestamp, nullable)
├── completed_at (timestamp, nullable)
├── files_purged_at (timestamp, nullable)
├── created_at, updated_at
│
├── INDEX (user_id, type, status)
└── INDEX (created_at)
```

#### Modificación a `users` (migración primaria, no backfill)

Editar `database/migrations/0001_01_01_000000_create_users_table.php` para agregar:

```php
$table->boolean('must_change_password')->default(false)->after('password');
```

Razón: la regla de proyecto (memory `feedback_edit_primary_migrations`) prohíbe migraciones de backfill mientras stg/prod no tengan datos reales. Editamos la migración primaria directamente; el primer `migrate:fresh --seed` recrea la columna.

### Enums

#### Nuevos enums PHP

`app/Enums/DataImportType.php`:

```php
namespace App\Enums;

enum DataImportType: string
{
    case Users = 'users';
    case ThirdParties = 'third_parties';
    case Drivers = 'drivers';
    case Vehicles = 'vehicles';

    public function label(): string
    {
        return match ($this) {
            self::Users => 'Usuarios',
            self::ThirdParties => 'Terceros',
            self::Drivers => 'Conductores',
            self::Vehicles => 'Vehículos',
        };
    }
}
```

`app/Enums/DataImportStatus.php`:

```php
namespace App\Enums;

enum DataImportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::Processing => 'Procesando',
            self::Completed => 'Completado',
            self::Failed => 'Falló',
        };
    }
}
```

#### Nuevo case en `Permission`

```php
// app/Enums/Permission.php — agregar el case y su label
case MANAGE_DATA_IMPORTS = 'data-imports.manage';

// label() → 'Gestionar importaciones masivas'
```

(54 permissions tras este cambio. Tras editar el enum, correr `./vendor/bin/sail artisan enum:typescript` para regenerar `resources/js/enums/Permission.ts`.)

### Routes

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/admin/imports` | `DataImportController@index` | `auth, verified, can:data-imports.manage` | `admin.imports.index` |
| GET | `/admin/imports/create` | `DataImportController@create` | idem | `admin.imports.create` |
| POST | `/admin/imports` | `DataImportController@store` | idem | `admin.imports.store` |
| GET | `/admin/imports/{import}` | `DataImportController@show` | idem | `admin.imports.show` |
| DELETE | `/admin/imports/{import}/files` | `DataImportController@purge` | idem | `admin.imports.purge` |
| GET | `/admin/imports/{import}/download/source` | `DataImportController@downloadSource` | idem | `admin.imports.download.source` |
| GET | `/admin/imports/{import}/download/errors` | `DataImportController@downloadErrors` | idem | `admin.imports.download.errors` |
| GET | `/admin/imports/templates/{type}` | `DataImportTemplateController@show` | idem; `where('type', 'users\|third-parties\|drivers\|vehicles')` | `admin.imports.templates.show` |
| GET | `/admin/imports/reference/{catalog}` | `DataImportReferenceController@show` | idem; `where('catalog', 'eps\|pension-funds\|severance-funds\|municipalities\|departments\|document-types\|incident-types')` | `admin.imports.reference.show` |

Todas dentro de un `Route::middleware(['auth', 'verified', 'can:data-imports.manage'])->prefix('admin/imports')->name('admin.imports.')->group(...)`.

`POST /admin/imports` debe aceptar el flag opcional `from_import_id` para reintentar un dry-run sin re-subir el archivo (ver AC16).

### Permissions

- **Nuevo**: `Permission::MANAGE_DATA_IMPORTS = 'data-imports.manage'`.
- **Asignación**: solo al rol Super Admin en `database/migrations/2026_03_13_000000_seed_catalog_data.php`. Si el seeder usa `givePermissionTo(Permission::cases())` para Super Admin, no se requiere edición; verificar y si usa enumeración explícita, agregar el case nuevo.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Index | `resources/js/pages/admin/imports/index.tsx` | **NEW.** Card "Plantillas" + card "Catálogos de referencia" + banner de retención + botón "Nueva carga" + tabla paginada del histórico (`<DataTable>` + `useServerTable` con columnas `Fecha · Tipo · Archivo · Estado · Resumen · Usuario · Acciones`). |
| Create | `resources/js/pages/admin/imports/create.tsx` | **NEW.** Form con `<Select>` Tipo, dropzone de archivo (CSV/XLSX, máx 20 MB con pre-check), 2 checkboxes (`dry_run`, `update_existing`) con hint text, submit "Subir y procesar". Inertia `useForm` + `<Form>`. |
| Show | `resources/js/pages/admin/imports/show.tsx` | **NEW.** Header (id, tipo, archivo, usuario, fecha) + status badge + banners condicionales (dry_run, purgado) + progress display (queued / contando / barra `X/Y`) + tabla de resultados (cuando terminal) + acciones (descargar fuente, descargar errores, reintentar como import real, eliminar archivos). `usePoll(2000, { only: ['import'], onlyWhen: !isTerminal })`. |
| Sidebar item | `resources/js/components/app-sidebar.tsx` | **EXTEND.** Agregar item `{ title: 'Importaciones', href: '/admin/imports', icon: Upload, permission: Permission.MANAGE_DATA_IMPORTS }` dentro del grupo `Administración`. |
| Retention banner | `resources/js/pages/admin/imports/index.tsx` | Card pequeña arriba del historial: "Los archivos se eliminan automáticamente 90 días después de completarse. El histórico se conserva indefinidamente." |
| Password-change page | `resources/js/pages/auth/change-password.tsx` | **REUSE/NEW.** Si ya existe un page de "primer cambio de contraseña" se reutiliza; si no, crear formulario simple (`current_password`, `password`, `password_confirmation`). El usuario llega via redirect del middleware. |

### Backend services / classes

#### `App\Models\DataImport`

- Traits: `HasFactory`, `LogsActivity` (Spatie).
- `$fillable`: `user_id, type, original_filename, disk, path, errors_path, status, dry_run, update_existing, rows_total, rows_processed, rows_created, rows_updated, rows_skipped, rows_errored, error_message, started_at, completed_at, files_purged_at`.
- `casts()`: `type => DataImportType::class`, `status => DataImportStatus::class`, `dry_run/update_existing => boolean`, `rows_* => integer`, `started_at/completed_at/files_purged_at => immutable_datetime`.
- Relación: `user(): BelongsTo` → `User`.
- Métodos: `isFinished(): bool` (status in [Completed, Failed]), `hasFiles(): bool` (`files_purged_at === null && path !== null`).
- `getActivitylogOptions()`: `LogOptions::defaults()->logOnly(['type', 'original_filename', 'status', 'dry_run', 'update_existing'])->logOnlyDirty()`.

#### `App\Http\Controllers\DataImportController`

7 acciones. Todas validan permiso vía middleware `can:data-imports.manage` en la ruta + `DataImportStoreRequest::authorize()` en `store`.

- `index(): Inertia\Response` — paginated `DataImport::with('user:id,name,email')->latest()->paginate(20)` + `types: DataImportType::cases()`.
- `create(): Inertia\Response` — `types: DataImportType::cases()`.
- `store(DataImportStoreRequest $request): RedirectResponse` — branch: si `from_import_id` viene, copia metadatos del origen y dispara nuevo import sin re-subir; si no, sube archivo a `s3://imports/{type}/{ulid}.{ext}`, crea fila, dispara `ProcessDataImportJob`.
- `show(DataImport $import): Inertia\Response` — `import` + `import.user`.
- `purge(DataImport $import): RedirectResponse` — borra archivos de s3, nullea `path`/`errors_path`, setea `files_purged_at`.
- `downloadSource(DataImport $import): StreamedResponse` — 410 si `!hasFiles()`; `Storage::disk($import->disk)->download($import->path, $import->original_filename)`.
- `downloadErrors(DataImport $import): StreamedResponse` — 404 si `errors_path === null`, 410 si `!hasFiles()`; `Storage::disk(...)->download($import->errors_path, "errores_{$import->id}.csv")`.

#### `App\Http\Controllers\DataImportTemplateController`

- `show(string $type): BinaryFileResponse` — sirve `database/csv/templates/{type_underscored}.csv` con `Content-Type: text/csv; charset=UTF-8` y nombre `plantilla_{type}.csv`. 404 si el archivo no existe.

#### `App\Http\Controllers\DataImportReferenceController`

- `show(string $catalog): StreamedResponse` — `match` que mapea slug → `[Model::class, columns, filename]`. Usa `streamDownload` con `fputcsv` iterando `Model::orderBy($columns[0])->each(...)`.

  Mapeo:
  - `eps` → `[Eps::class, ['code', 'name'], 'eps.csv']`
  - `pension-funds` → `[PensionFund::class, ['code', 'name'], 'pension_funds.csv']`
  - `severance-funds` → `[SeveranceFund::class, ['code', 'name'], 'severance_funds.csv']`
  - `municipalities` → `[Municipality::class, ['code', 'name', 'department_code'], 'municipalities.csv']`
  - `departments` → `[Department::class, ['code', 'name'], 'departments.csv']`
  - `document-types` → `[DocumentType::class, ['code', 'name'], 'document_types.csv']`
  - `incident-types` → `[IncidentType::class, ['code', 'name'], 'incident_types.csv']`

#### `App\Http\Requests\DataImportStoreRequest`

```php
public function authorize(): bool
{
    return Gate::allows(Permission::MANAGE_DATA_IMPORTS);
}

public function rules(): array
{
    $rules = [
        'type' => ['required', Rule::enum(DataImportType::class)],
        'dry_run' => ['boolean'],
        'update_existing' => ['boolean'],
        'from_import_id' => ['nullable', 'integer', 'exists:data_imports,id'],
    ];

    // CSV es requerido SOLO cuando NO viene from_import_id
    if (! $this->filled('from_import_id')) {
        $rules['csv'] = ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx'];
    }

    return $rules;
}

public function messages(): array
{
    return [
        'csv.max' => 'El archivo excede el límite de 20 MB.',
        'csv.mimes' => 'Solo se aceptan archivos CSV o XLSX.',
        'type.enum' => 'Tipo de carga inválido.',
    ];
}
```

#### `App\Jobs\ProcessDataImportJob`

- `implements ShouldQueue`, usa trait `Queueable`.
- `public string $queue = 'imports'`, `public int $tries = 1`, `public int $timeout = 1800`.
- Constructor inyecta `public DataImport $import`.
- `handle(ImporterRegistry $registry)`: actualiza `status=processing` + `started_at`, resuelve importer concreto, baja archivo a temp local si disco no es local (`ensureLocalCopy`), valida header, cuenta filas, setea `rows_total`, abre `SimpleExcelWriter` para errors a temp local, llama `importer->processFile(...)` con callback de progreso (update DB cada 200 filas), si hay errores sube `errors.csv` a `s3://imports/{type}/{ulid}_errors.csv`, marca `status=completed` + `completed_at`.
- `failed(Throwable $e)`: actualiza `status=failed`, `error_message=substr($e->getMessage(), 0, 1000)`, `completed_at=now()`, `Log::error('ProcessDataImportJob failed', [import_id, exception])`.

#### `App\Services\Imports\AbstractImporter` (clase abstracta)

Define la interfaz contractual y la maquinaria compartida (header check, count rows, processFile con chunks, processChunk con dedup + transacción).

Métodos abstractos:
- `expectedHeaders(): array<string>`
- `naturalKey(): string`
- `rules(): array<string, array>`
- `messages(): array<string, string>`
- `transformRow(array $row): array` (puede tirar `RowTransformException`)
- `findExisting(string $naturalKeyValue): ?Model`
- `persistNew(array $data): Model`
- `applyUpdate(Model $existing, array $data): Model`

Métodos compartidos:
- `validateHeader(string $path): HeaderCheck` — usa `SimpleExcelReader::create($path)->getRows()->first()` para obtener actual headers.
- `countRows(string $path): int` — CSV: `substr_count` chunked (buffer 64 KB); XLSX: iteración streaming con `simple-excel`.
- `processFile(SimpleExcelReader, DataImport, SimpleExcelWriter $errorsWriter, Closure $onProgress): void` — itera el reader llenando chunks de 200 filas, llama `processChunk` por cada uno.
- `processChunk(...)` — `DB::transaction` que para cada fila: valida (Validator), dedup vía `$seenKeys`, transforma, decide create/update/skip según `findExisting` + `import.update_existing`, en `dry_run` solo cuenta, en real persiste.

Soporte:

```php
final readonly class HeaderCheck
{
    public function __construct(public bool $ok, public ?string $error = null) {}
}

class RowTransformException extends \RuntimeException {}
```

#### Concreto: `UserImporter`

- `expectedHeaders()`: `['email', 'name', 'role', 'password']`
- `naturalKey()`: `'email'`
- `rules()`: `email => required|email|max:255`, `name => required|string|max:100`, `role => required|in:admin,operator,driver,accounting` (NO super_admin), `password => nullable|string|min:8|max:255`.
- `transformRow()`: si `password` vacío genera `Str::password(16)` y marca flag `must_change_password = true`; si viene, `must_change_password = false`. Hashea con `bcrypt`. Resuelve `role` → asignación posterior con Spatie en `persistNew`/`applyUpdate`.
- `persistNew()`: `User::create(...)` + `$user->assignRole($role)`.
- `applyUpdate()`: `$existing->update(...)` + `$existing->syncRoles([$role])`.

#### Concreto: `ThirdPartyImporter`

- `expectedHeaders()`: `['identification_type', 'identification_number', 'name', 'type', 'phone', 'email', 'address', 'municipality_code']`
- `naturalKey()`: `'identification_number'`
- `rules()`: ver §10 del draft (`identification_type` in NIT,CC,CE,PA; `type` in client,provider,both; `municipality_code` nullable + exists:municipalities,code; etc.).
- `transformRow()`: resuelve `municipality_code` → `municipality_id` con `Municipality::where('code', $code)->first()` (nullable).
- `persistNew()/applyUpdate()`: `ThirdParty::create / $existing->update`.

#### Concreto: `DriverImporter`

- `expectedHeaders()`: `['identification_type', 'identification_number', 'first_name', 'first_lastname', 'second_lastname', 'birth_date', 'license_number', 'license_category', 'license_due_date', 'eps_code', 'pension_fund_code', 'severance_fund_code', 'has_social_security', 'user_email', 'municipality_code']`
- `naturalKey()`: `'identification_number'`
- `rules()`: `birth_date / license_due_date => required|date_format:Y-m-d`, `license_category => required|in:A1,A2,B1,B2,B3,C1,C2,C3` (verificar enum `LicenseCategory.php` real), `eps_code => required|string|exists:eps,code`, `pension_fund_code => required|exists:pension_funds,code`, `severance_fund_code => required|exists:severance_funds,code`, `has_social_security => required|boolean`, `user_email => nullable|email|exists:users,email`, `municipality_code => nullable|exists:municipalities,code`.
- `transformRow()`: resuelve todos los `*_code` → `*_id` con queries `firstOrFail` (FK rota lanza `RowTransformException`); resuelve `user_email` → `user_id` y verifica que el user tenga rol `driver` (si no, `RowTransformException`).
- `persistNew()/applyUpdate()`: `Driver::create / $existing->update`.

#### Concreto: `VehicleImporter`

- `expectedHeaders()`: `['plate', 'internal_code', 'type', 'brand', 'model', 'year', 'is_third_party', 'third_party_identification', 'soat_due_date', 'rtm_due_date', 'operation_card_due_date']`
- `naturalKey()`: `'plate'`
- `rules()`: `plate => required|string|max:6|regex:/^[A-Z]{3}[0-9]{3}$/i`, `type => required` con valores del enum `VehicleType` real (verificar), `year => required|integer|min:1980|max:2030`, `is_third_party => required|boolean`, `third_party_identification => required_if:is_third_party,1|exists:third_parties,identification_number`, `soat_due_date / rtm_due_date => required|date_format:Y-m-d`, `operation_card_due_date => nullable|date_format:Y-m-d`.
- `transformRow()`: si `is_third_party`, resuelve `third_party_identification` → `third_party_id`; normaliza `plate` a uppercase.
- `persistNew()/applyUpdate()`: `Vehicle::create / $existing->update`.

#### `App\Services\Imports\ImporterRegistry`

```php
class ImporterRegistry
{
    public function __construct(
        private readonly UserImporter $users,
        private readonly ThirdPartyImporter $thirdParties,
        private readonly DriverImporter $drivers,
        private readonly VehicleImporter $vehicles,
    ) {}

    public function for(DataImportType $type): AbstractImporter
    {
        return match ($type) {
            DataImportType::Users => $this->users,
            DataImportType::ThirdParties => $this->thirdParties,
            DataImportType::Drivers => $this->drivers,
            DataImportType::Vehicles => $this->vehicles,
        };
    }
}
```

#### `App\Http\Middleware\EnsurePasswordChanged`

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    if ($user && $user->must_change_password) {
        $allowed = ['password.change', 'password.update', 'logout'];
        if (! in_array($request->route()?->getName(), $allowed) && ! $request->expectsJson()) {
            return redirect()->route('password.change');
        }
    }
    return $next($request);
}
```

Registrar en `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [\App\Http\Middleware\EnsurePasswordChanged::class]);
})
```

(Si ya existe una página de cambio de contraseña en settings, reutilizarla y poner alias `Route::name('password.change')`. Si no, crearla.)

#### `App\Console\Commands\PurgeOldImportFiles`

- Signature: `imports:purge-old-files`.
- Lógica: itera `DataImport::whereNotNull('path')->whereNull('files_purged_at')->where('completed_at', '<', now()->subDays(90))->each(...)` borrando archivos y nulleando columnas.

#### `App\Console\Commands\ReapStuckImports`

- Signature: `imports:reap-stuck`.
- Lógica: `DataImport::where('status', 'processing')->where('started_at', '<', now()->subMinutes(35))->update([...])`.

#### Schedules en `routes/console.php`

```php
Schedule::command('imports:purge-old-files')->dailyAt('03:00');
Schedule::command('imports:reap-stuck')->everyFiveMinutes();
```

### Templates estáticas

Ubicación: `database/csv/templates/`.

```
database/csv/templates/
├── README.md            # diccionario de columnas + versionado
├── users.csv
├── third_parties.csv
├── drivers.csv
└── vehicles.csv
```

Encoding UTF-8. Formato fechas `YYYY-MM-DD`. Booleanos `1` / `0`.

`users.csv`:
```csv
email,name,role,password
juan.perez@cliente.co,Juan Pérez,admin,
ana.lopez@cliente.co,Ana López,operator,Temporal2026!
```

`third_parties.csv`:
```csv
identification_type,identification_number,name,type,phone,email,address,municipality_code
NIT,900123456,Empresa SA,client,3001234567,contacto@empresa.co,Cra 1 #2-3,11001
```

`drivers.csv`:
```csv
identification_type,identification_number,first_name,first_lastname,second_lastname,birth_date,license_number,license_category,license_due_date,eps_code,pension_fund_code,severance_fund_code,has_social_security,user_email,municipality_code
CC,1023456789,Carlos,Ramírez,Pérez,1990-05-12,LIC123456,C1,2027-12-31,EPS001,PF001,SF001,1,driver@cliente.co,11001
```

`vehicles.csv`:
```csv
plate,internal_code,type,brand,model,year,is_third_party,third_party_identification,soat_due_date,rtm_due_date,operation_card_due_date
ABC123,V001,Bus,Chevrolet,NPR,2020,0,,2026-12-31,2026-08-15,2027-03-01
```

`README.md`:
- Sección por entidad con la tabla de columnas (tipo, requerido, notas).
- Ejemplo realista por entidad.
- Notas sobre encoding y formato.
- Versionado: `users.csv v1 (2026-04-27): versión inicial`.

### Configuración Horizon

Modificar `config/horizon.php` agregando `supervisor-imports` en cada environment:

```php
'environments' => [
    'production' => [
        'supervisor-default' => [ /* ... existente ... */ ],
        'supervisor-imports' => [
            'maxProcesses' => 1,
            'queue' => ['imports'],
            'memory' => 256,
            'tries' => 1,
            'timeout' => 1800,
            'balance' => 'simple',
        ],
    ],
    'staging' => [ /* mismo bloque que production */ ],
    'local' => [
        'supervisor-default' => [ /* ... existente ... */ ],
        'supervisor-imports' => [
            'maxProcesses' => 1,
            'queue' => ['imports'],
            'memory' => 256,
            'tries' => 1,
            'timeout' => 1800,
        ],
    ],
],
```

### Configuración PHP (Docker producción)

Crear `docker/production/php-uploads.ini`:

```ini
upload_max_filesize = 25M
post_max_size = 30M
memory_limit = 256M
```

Modificar `docker/production/Dockerfile` (stage `production`, después del `COPY` del start-container):

```dockerfile
COPY docker/production/php-uploads.ini /usr/local/etc/php/conf.d/php-uploads.ini
```

### Sidebar

En `resources/js/components/app-sidebar.tsx`, dentro del grupo `Administración` (existente, gateado a Admin), agregar el item:

```tsx
{
    title: 'Importaciones',
    href: '/admin/imports',
    icon: Upload, // de lucide-react
    permission: Permission.MANAGE_DATA_IMPORTS,
},
```

El permiso `MANAGE_DATA_IMPORTS` filtra el item para que solo Super Admin lo vea (por ahora). El grupo `Administración` ya está gateado a Admin pero el `<Can>` interno hace el doble filtro.

## Migration Strategy

`modify-existing`. Razón: la regla del proyecto prohíbe migraciones de backfill mientras stg/prod no tengan datos reales (memory `feedback_edit_primary_migrations`). El cambio en `users` se hace editando la migración primaria `0001_01_01_000000_create_users_table.php`. Se requiere `./vendor/bin/sail artisan migrate:fresh --seed` después del cambio.

Adicionalmente, una migración nueva para `data_imports`: `database/migrations/YYYY_MM_DD_HHMMSS_create_data_imports_table.php`.

## Tasks

### Backend

- [x] **B1: Modificar migración primaria `users`** — agregar `$table->boolean('must_change_password')->default(false)->after('password')` a `database/migrations/0001_01_01_000000_create_users_table.php`. Actualizar `$fillable` y `casts()` en `app/Models/User.php`. Confirmar que `php artisan migrate:fresh --seed` sigue verde.
- [x] **B2: Crear migración `create_data_imports_table`** — `php artisan make:migration create_data_imports_table` con el schema definido en §Data Model (todos los campos, FK CASCADE a users, 2 índices).
- [x] **B3: Crear enums** — `app/Enums/DataImportType.php` y `app/Enums/DataImportStatus.php` con `label()` en español.
- [x] **B4: Agregar permiso** — `Permission::MANAGE_DATA_IMPORTS = 'data-imports.manage'` con label "Gestionar importaciones masivas". Actualizar `database/migrations/2026_03_13_000000_seed_catalog_data.php` para asignar a Super Admin (verificar si ya itera `Permission::cases()`). Correr `./vendor/bin/sail artisan enum:typescript`.
- [x] **B5: Crear modelo `DataImport`** — `php artisan make:model DataImport -f` con casts, relación `user()`, `LogsActivity` trait + `getActivitylogOptions()`, métodos `isFinished()` y `hasFiles()`.
- [x] **B6: Crear factory `DataImportFactory`** — `database/factories/DataImportFactory.php` con definición base + states `queued()`, `processing()`, `completed()`, `failed()`, `dryRun()`, `withErrors()`, `purged()`.
- [x] **B7: Instalar paquete** — `./vendor/bin/sail composer require spatie/simple-excel`.
- [x] **B8: Crear `AbstractImporter` + soporte** — `app/Services/Imports/AbstractImporter.php`, `HeaderCheck.php`, `RowTransformException.php`. Métodos compartidos `validateHeader`, `countRows` (CSV vía `substr_count`, XLSX vía iteración), `processFile`, `processChunk` con dedup `$seenKeys` + `DB::transaction`.
- [x] **B9: Crear 4 importers concretos** — `UserImporter`, `ThirdPartyImporter`, `DriverImporter`, `VehicleImporter` en `app/Services/Imports/`. Cada uno define `expectedHeaders`, `naturalKey`, `rules`, `messages`, `transformRow`, `findExisting`, `persistNew`, `applyUpdate`. Verificar enums `LicenseCategory`, `VehicleType` reales del proyecto al definir reglas `in:`.
- [x] **B10: Crear `ImporterRegistry`** — `app/Services/Imports/ImporterRegistry.php` con constructor que inyecta los 4 importers y `for(DataImportType): AbstractImporter`.
- [x] **B11: Crear `ProcessDataImportJob`** — `php artisan make:job ProcessDataImportJob`. `$queue = 'imports'`, `$tries = 1`, `$timeout = 1800`. `handle(ImporterRegistry)` con flujo completo (status=processing, header check, countRows, processFile con onProgress callback cada 200 filas, subir errors.csv si hay, marcar completed). Método `failed(Throwable)` que actualiza fila a failed.
- [x] **B12: Crear `DataImportStoreRequest`** — `php artisan make:request DataImportStoreRequest`. `authorize()` con `Gate::allows`, `rules()` condicionales (csv requerido solo si `from_import_id` no viene), `messages()`.
- [x] **B13: Crear `DataImportController`** — `php artisan make:controller DataImportController`. 7 acciones con la lógica especificada. `store()` rama `from_import_id` que copia metadatos sin re-subir archivo y dispara nuevo job.
- [x] **B14: Crear `DataImportTemplateController`** — `php artisan make:controller DataImportTemplateController`. Acción `show(string $type)` que sirve el CSV estático.
- [x] **B15: Crear `DataImportReferenceController`** — `php artisan make:controller DataImportReferenceController`. Acción `show(string $catalog)` con `streamDownload` y `match` para los 7 catálogos.
- [x] **B16: Registrar rutas** — agregar el grupo `admin/imports` en `routes/web.php` con todas las 9 rutas, middlewares `auth, verified, can:data-imports.manage`.
- [x] **B17: Crear comandos** — `php artisan make:command PurgeOldImportFiles` y `ReapStuckImports`. Schedules en `routes/console.php` (`dailyAt('03:00')` y `everyFiveMinutes()`).
- [x] **B18: Crear middleware `EnsurePasswordChanged`** — `php artisan make:middleware EnsurePasswordChanged`. Registrar en `bootstrap/app.php` con `web(append: [...])`. Verificar que existe una ruta `password.change` (settings) o crearla.
- [x] **B19: Configurar Horizon** — agregar `supervisor-imports` en `config/horizon.php` para production, staging, local.
- [x] **B20: Configurar PHP uploads** — crear `docker/production/php-uploads.ini` con los 3 valores y agregar el `COPY` al stage `production` del `Dockerfile`. Verificar localmente con `docker run --rm --entrypoint php sgte-app:latest -i | grep -E '^(upload_max_filesize|post_max_size|memory_limit)'`.

### Frontend

- [x] **F1: Crear plantillas estáticas** — `database/csv/templates/{users,third_parties,drivers,vehicles}.csv` con header + 1-2 filas ejemplo. `README.md` con tabla de columnas por entidad + versionado.
- [x] **F2: Crear `index.tsx`** — `resources/js/pages/admin/imports/index.tsx`. Usa `<DataTable>` + `useServerTable`. Card "Plantillas" (4 botones) + card "Catálogos" (7 links a endpoints reference). Banner de retención "Los archivos se eliminan automáticamente 90 días después de completarse. El histórico se conserva indefinidamente." Botón "Nueva carga" linkea a `/admin/imports/create`. Tabla histórica con columnas `Fecha · Tipo · Archivo · Estado (badge) · Resumen (`+N ~M ⊘P ✗Q`) · Usuario · Acciones (Ver detalle)`. Breadcrumbs `Administración › Importaciones`.
- [x] **F3: Crear `create.tsx`** — `resources/js/pages/admin/imports/create.tsx`. Form con `useForm` + `<Form>`. `<Select>` Tipo (4 opciones del enum), input `<File>` con dropzone (CSV/XLSX, pre-check 20 MB en `handleFileChange`), 2 `<Checkbox>` con hints especificados. Botones "Cancelar" + "Subir y procesar" (disabled hasta que tipo + archivo seleccionados). Breadcrumbs `Administración › Importaciones › Nueva carga`.
- [x] **F4: Crear `show.tsx`** — `resources/js/pages/admin/imports/show.tsx`. Header (id, tipo, archivo, usuario, fechas), status badge grande con icono, banner amarillo si `dry_run`, banner gris si `files_purged_at`, progress display (queued / spinner contando / `<Progress>` con `X / Y (Z%)`), tabla de resultados cuando terminal (creados/actualizados/saltados/errados), card rojo con `error_message` si `failed`, sección de acciones (`Descargar archivo original`, `Descargar errores`, `Reintentar como import real` solo si `completed && dry_run && hasFiles`, `Eliminar archivos` con `confirm` modal). `usePoll(2000, { only: ['import'], onlyWhen: !isTerminal })`. Reintento envía `POST /admin/imports` con `from_import_id`. Breadcrumbs `Administración › Importaciones › #{id}`.
- [x] **F5: Sidebar item** — `resources/js/components/app-sidebar.tsx`: agregar item "Importaciones" en el grupo `Administración` con icon `Upload` y permiso `Permission.MANAGE_DATA_IMPORTS`.
- [x] **F6: Página de cambio de contraseña** — verificar si existe en `resources/js/pages/auth/` o `resources/js/pages/settings/`. Si no, crear `resources/js/pages/auth/change-password.tsx` con form (`current_password`, `password`, `password_confirmation`). Asegurar que la ruta nombrada `password.change` apunta a esta página y la ruta `password.update` (POST) actualiza el password + setea `must_change_password = false`.

### Tests

- [x] **T1: `DataImportControllerTest`** — `tests/Feature/Http/Controllers/DataImportControllerTest.php`. Cubrir: index renderiza con paginación; create renderiza tipos; store con archivo válido crea fila + dispara job (`Bus::fake`); store con archivo > 20 MB → 422; store con mime inválido → 422; store con `from_import_id` clona metadatos del origen sin requerir csv y crea NUEVA fila; show renderiza prop `import`; purge borra archivos y nullea path; downloadSource retorna 410 si purgado; downloadErrors retorna 404 si `errors_path` null; **autorización**: admin/operator/driver/accounting/invitado reciben 403 o 401 en cada acción; super admin pasa.
- [x] **T2: `DataImportTemplateControllerTest`** — descarga de cada plantilla retorna 200 con `Content-Type: text/csv`; tipo inválido (`xyz`) retorna 404; gating super admin (admin recibe 403).
- [x] **T3: `DataImportReferenceControllerTest`** — exporta CSV de cada uno de los 7 catálogos con headers correctos y rows ordenadas por `code` ASC; catálogo inválido retorna 404; gating super admin.
- [x] **T4: `UserImporterTest`** — `tests/Feature/Services/Imports/UserImporterTest.php`. Header válido pasa; header con columna faltante falla con mensaje claro; fila válida con email nuevo y password vacío → `created`, `Hash::check` con random + `must_change_password=true`; fila con password explícito → `created` con `must_change_password=false`; fila con email existente y `update_existing=false` → `skipped`; fila con email existente y `update_existing=true` → `updated`; fila con `role=super_admin` → `errored`; dos filas con mismo email → segunda `errored`; dry_run no persiste pero counters reflejan.
- [x] **T5: `ThirdPartyImporterTest`** — análogo: header, claves nuevas/existentes, FK municipality_code rota → errored, dedup, dry_run.
- [x] **T6: `DriverImporterTest`** — análogo + verificar resolución `eps_code/pension_fund_code/severance_fund_code/user_email` a IDs; user_email con rol incorrecto → `errored`; license_category fuera de enum → `errored`; dedup.
- [x] **T7: `VehicleImporterTest`** — análogo + verificar `is_third_party=1` requiere `third_party_identification`; FK third_party_identification rota → errored; plate normalizada a uppercase; dedup.
- [x] **T8: `ProcessDataImportJobTest`** — `tests/Feature/Jobs/ProcessDataImportJobTest.php`. Marca processing → completed con counts correctos para CSV con 5 filas; marca failed con error_message si header inválido; escribe errors.csv en MinIO con filas erradas; actualiza `rows_processed` periódicamente (chunk size temporalmente reducido vía property override); `failed(Throwable)` marca status=failed con mensaje truncado a 1000 chars. Usar `Storage::fake('s3')`.
- [x] **T9: `PurgeOldImportFilesTest` + `ReapStuckImportsTest`** — `tests/Feature/Console/`. Purge: borra archivos y nullea path para imports >90 días, ignora recientes y ya purgados. Reap: marca como failed los con `started_at < now() - 35 min` AND `status = processing`, ignora otros estados.
- [x] **T10: `EnsurePasswordChangedMiddlewareTest`** — `tests/Feature/Middleware/`. Usuario con flag y request a `/dashboard` redirige a `/password/change`; usuario con flag y request a `/password/change` o `/logout` pasa; request JSON (Inertia partial) NO redirige; usuario sin flag pasa siempre; usuario no autenticado no se ve afectado.
- [x] **T11: `AdminDataImportsBrowserTest` (Dusk)** — `tests/Browser/AdminDataImportsTest.php`. Antes de cada test: `php artisan migrate:fresh --seed --no-interaction`. Casos:
  - **Happy path**: super admin loguea, navega a `/admin/imports`, ve banner de retención, descarga plantilla `users.csv`, verifica download. Click "Nueva carga", selecciona tipo, sube CSV pequeño (5 filas), submit, redirige a show. Polling actualiza progress hasta `completed`. Verifica counters `+5 ~0 ⊘0 ✗0`. Asserts visuales: ningún error banner, headings/labels en español ("Importaciones", "Nueva carga", "Plantillas"), columnas correctas en la tabla histórica.
  - **Auth**: logout, login como `admin@sgte.app`, navega a `/admin/imports` → 403 (o sidebar item ausente). Mismo para operator, driver, accounting.
  - **Dry-run**: super admin sube CSV con `dry_run=true`. Verifica banner amarillo "Solo validación — no se persistió ningún registro". `User::count()` no aumenta. Click "Reintentar como import real" → nueva fila aparece, status `queued/processing/completed`, registros aparecen en `/users`.
  - **Errores**: subir CSV con 2 filas inválidas + 3 válidas. Verifica `+3 ⊘0 ✗2`. Click "Descargar errores", verifica `errors.csv` descargado.
  - Screenshots en cada step crítico (post-login, post-upload, post-completed, post-retry, post-errors).

## Verification

### 1. Interactive verification — Playwright MCP

Reference users (password `password`, super admin desde `SUPER_ADMIN_USER` / `SUPER_ADMIN_PASSWORD` en `.env`):

| Role | Email |
|---|---|
| Super Admin | (env `SUPER_ADMIN_USER`) |
| Admin | `admin@sgte.app` |
| Operator | `operator@sgte.app` |
| Driver | `driver@sgte.app` |
| Accounting | `accounting@sgte.app` |

Antes de cada smoke: `./vendor/bin/sail artisan migrate:fresh --seed`. Encender Horizon: `./vendor/bin/sail artisan horizon`.

- [x] Scenario 1: super admin navega a `/admin/imports`, ve la página completa (plantillas, catálogos, banner retención, botón Nueva carga, tabla vacía). `browser_snapshot`.
- [x] Scenario 2: descarga `plantilla_users.csv`, abre el archivo, verifica header y ejemplos. `browser_take_screenshot` de la página index.
- [x] Scenario 3: descarga `eps.csv` desde catálogos de referencia; verifica que el contenido coincide con `Eps::orderBy('code')->get()` vía `mcp__laravel-boost__database-query`.
- [x] Scenario 4: subir CSV de 100 conductores válidos (puede generarse con `Driver::factory()->count(100)->make()->toArray()` y export a CSV). Verificar progress bar avanza. Estado final `completed` con `+100 ~0 ⊘0 ✗0`. Verificar conductores aparecen en `/drivers`.
- [x] Scenario 5: subir mismo CSV con `update_existing=false` → todos `skipped`. Subir mismo CSV con `update_existing=true` → todos `updated`.
- [x] Scenario 6: subir CSV con 5 filas erradas (FK rota, email inválido, license_category fuera de enum). Verificar `errored=5`, descargar `errors.csv`, abrir y validar mensajes en español.
- [x] Scenario 7: subir CSV con `dry_run=true`. Verificar banner amarillo. `Driver::count()` no cambia (consulta vía `database-query`). Click "Reintentar como import real", verificar nueva fila + persistencia real.
- [x] Scenario 8: como admin (no super admin), navegar a `/admin/imports` → 403 / sidebar item ausente. `browser_snapshot` confirma item no aparece.
- [x] Scenario 9: subir `users.csv` con un usuario nuevo y password vacío. Logout. Login como ese usuario con password autogenerada (consultar via `tinker`). Verificar redirect inmediato a `/password/change`. Cambiar password. Verificar `must_change_password=false` y acceso normal.
- [x] Scenario 10: leer logs de browser con `mcp__laravel-boost__browser-logs` durante el flujo completo — no debe haber errores JS.

### 2. Backend regression — Pest feature tests (required)

Tests obligatorios listados en T1–T10. Run:

```bash
./vendor/bin/sail test --compact tests/Feature/Http/Controllers/DataImportControllerTest.php
./vendor/bin/sail test --compact tests/Feature/Http/Controllers/DataImportTemplateControllerTest.php
./vendor/bin/sail test --compact tests/Feature/Http/Controllers/DataImportReferenceControllerTest.php
./vendor/bin/sail test --compact tests/Feature/Services/Imports/
./vendor/bin/sail test --compact tests/Feature/Jobs/ProcessDataImportJobTest.php
./vendor/bin/sail test --compact tests/Feature/Console/
./vendor/bin/sail test --compact tests/Feature/Middleware/EnsurePasswordChangedMiddlewareTest.php

# Suite completa
./vendor/bin/sail test --compact
```

- [x] Toda la suite Pest verde, incluyendo los nuevos tests.

### 3. UI regression — Laravel Dusk browser tests (required)

Test obligatorio: T11 (`tests/Browser/AdminDataImportsTest.php`). Ejecuta:

```bash
./vendor/bin/sail dusk --filter=AdminDataImports
```

El test DEBE asertar:
- Página renderiza sin error banners ni stack traces.
- Headings y labels en español: "Importaciones", "Nueva carga", "Plantillas", "Catálogos de referencia", "Historial", "Tipo de carga", "Solo validar (no escribir cambios)", "Actualizar registros existentes", "Subir y procesar".
- Columnas correctas en la tabla del histórico: Fecha, Tipo, Archivo, Estado, Resumen, Usuario, Acciones.
- Status badge muestra el texto correcto en español según `DataImportStatus::label()`.
- Banner de retención visible.
- Acciones condicionales (Reintentar como import real solo en dry_run completed).
- Screenshots en cada paso crítico.

- [x] `./vendor/bin/sail dusk --filter=AdminDataImports` pasa localmente.

### 4. API endpoints — curl

No hay endpoints API públicos en este requerimiento (todo es Inertia). Para verificar el contrato HTTP sin la SPA:

```bash
# Login como super admin
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d '{"email":"'"$SUPER_ADMIN_USER"'","password":"'"$SUPER_ADMIN_PASSWORD"'"}' \
  -c cookies.txt

# Index
curl -s -X GET http://localhost/admin/imports \
  -H "Accept: text/html, application/xhtml+xml" \
  -H "X-Inertia: true" \
  -b cookies.txt | jq '.props | keys'

# Descargar plantilla
curl -s -X GET http://localhost/admin/imports/templates/users \
  -b cookies.txt -o /tmp/plantilla_users.csv
head -1 /tmp/plantilla_users.csv  # debe ser: email,name,role,password

# Descargar catálogo eps
curl -s -X GET http://localhost/admin/imports/reference/eps \
  -b cookies.txt -o /tmp/eps.csv
head -1 /tmp/eps.csv  # debe ser: code,name

# Login como admin (no super admin) y verificar 403
curl -s -X POST http://localhost/login -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admin@sgte.app","password":"password"}' -c cookies-admin.txt

curl -s -o /dev/null -w "%{http_code}" -X GET http://localhost/admin/imports \
  -H "Accept: application/json" -b cookies-admin.txt
# Debe imprimir 403
```

## Dependencies

- **Paquete nuevo**: `spatie/simple-excel ^3` (wrappea `openspout/openspout` para CSV+XLSX en streaming).
- **Catálogos requeridos en DB**: `eps`, `pension_funds`, `severance_funds`, `municipalities`, `departments`, `document_types`, `incident_types` deben estar poblados (ya lo están vía catalog migration).
- **Modelos requeridos**: `User`, `ThirdParty`, `Driver`, `Vehicle`, `Eps`, `PensionFund`, `SeveranceFund`, `Municipality`, `Department`, `DocumentType`, `IncidentType` (todos existen).
- **Disco s3 configurado**: `config/filesystems.php` debe tener disco `s3` apuntando a MinIO en stg/prod (ya está). En tests se usa `Storage::fake('s3')`.
- **Horizon corriendo**: el job NO procesa hasta que `php artisan horizon` esté arriba en el ambiente de prueba.
- **Super Admin existente**: la UI no se muestra si no hay super admin (asunción documentada — no se incluye lógica de bootstrap en este requerimiento).
- **Mailpit / no email**: este requerimiento NO envía email de bienvenida; el flujo es redirect-on-first-login.

## Notes

### Decisiones cerradas durante Q&A (referencia)

| Decisión | Tomada |
|---|---|
| Polling 2s vs Reverb broadcast | Polling con `usePoll(2000)` |
| Soporte de `dry_run` | Sí, single-path con flag (avoid drift) |
| Retención de fila | Para siempre (audit trail) |
| Retención de archivos | 90 días + purga manual + banner persistente en index |
| Permiso para usar | `MANAGE_DATA_IMPORTS = data-imports.manage` solo a super admin |
| Plantillas en repo | Estáticas CSV en `database/csv/templates/` |
| Catálogos de referencia | Endpoints dinámicos (no archivos estáticos) |
| Formato | CSV + XLSX vía `spatie/simple-excel` |
| Bulk insert | Per-row save en chunks transaccionales (preserva Eloquent events + ActivityLog + mutators) |
| Conteo de filas | CSV vía `substr_count` chunked, XLSX vía iteración con simple-excel |
| Cap de archivo | 20 MB |
| Cap de filas | sin tope explícito (lo que entre en 20 MB) |
| Cola Horizon | Supervisor `supervisor-imports` propio, maxProcesses=1, tries=1, timeout=30min |
| Bootstrap inicial | Sin cambios; super admin asumido vía catalog migration |
| Path del INI override | `docker/production/php-uploads.ini` → `/usr/local/etc/php/conf.d/php-uploads.ini` |
| Password autogenerado en `users.csv` | Sí + flag `must_change_password` + middleware redirect |
| Reintentar como import real | `POST /admin/imports` con `from_import_id` (nueva fila, audit linaje preservado) |
| Factory `DataImportFactory` | Sí, con states `queued/processing/completed/failed/dryRun/withErrors/purged` |

### Edge cases (a validar en tests)

| Caso | Tratamiento esperado |
|---|---|
| CSV con BOM UTF-8 | `simple-excel` lo maneja transparentemente |
| CSV con separador `;` | configurar `useDelimiter(';')` o auto-detect (verificar con muestra) |
| CSV con `\n` dentro de quoted field | `substr_count` puede sobrecontar; aceptamos error en denominador, procesamiento real correcto |
| XLSX con múltiples sheets | leemos solo la primera (decisión documentada en `README.md` de templates) |
| XLSX con celdas merged en header | rompe `expectedHeaders()` check; mensaje claro en error |
| XLSX con fechas como serial Excel | falla validación `date_format`, fila va a errored |
| Subida concurrente | imposible (`maxProcesses=1` serializa en Horizon) |
| Cliente cierra browser durante procesamiento | job sigue, vuelve a `/admin/imports/{id}` y ve estado |

### Seguridad y PII

- Los CSVs contienen datos personales (cédulas, nombres, fechas de nacimiento, emails, teléfonos). MinIO en producción debe tener bucket privado.
- Logs del job NO contienen contenido de filas — solo `import_id` y mensaje de excepción.
- `errors.csv` SÍ contiene la fila cruda en `original_data` (necesario para que el usuario corrija). Está en MinIO privado, gateado por `MANAGE_DATA_IMPORTS`.
- Purga manual permite responder a derecho-a-supresión sin esperar 90 días.
- Activity log registra metadata del `DataImport` (sin contenido).

### Riesgos

| Riesgo | Mitigación |
|---|---|
| Cliente sube archivo gigante por error | Cap 20 MB + frontend pre-check |
| Cliente sube formato inesperado | Header validation early con mensaje claro |
| Cliente sube datos inválidos | Per-row validation, errors.csv descargable |
| Worker colgado | timeout 30 min + janitor cada 5 min |
| Dos super admins suben simultáneamente | maxProcesses=1 serializa |
| FK roto al persistir | Validator `exists:` + `firstOrFail` en transformRow |
| MinIO no disponible | Validation falla en store(); job falla y `failed()` marca status |
| Datos sensibles en logs | Job loggea solo metadata; row data va solo a errors.csv en MinIO privado |

### Plan de despliegue (commits sugeridos por PR)

1. `feat(imports): 🗃️ data_imports migration + DataImport model + enums + permission`
2. `feat(imports): 🔧 importers + ProcessDataImportJob + Horizon supervisor`
3. `feat(imports): ✨ controllers + routes + FormRequest`
4. `feat(imports): 💄 admin pages (index/create/show) + sidebar`
5. `feat(imports): 📦 static templates + reference catalog endpoints`
6. `feat(users): 🔒 must_change_password column + middleware + change-password page`
7. `chore(docker): 🐳 php-uploads.ini override for production`
8. `test(imports): ✅ Pest controllers + importers + job + commands`
9. `test(imports): ✅ Dusk happy path + auth + dry_run + errors`

Cada PR atómico con su propio batch de tests.
