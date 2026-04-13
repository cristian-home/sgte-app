<?php

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Registers the MANAGE_CATALOGS permission and grants it to the admin role.
 *
 * Runs in all environments. Idempotent via firstOrCreate and givePermissionTo
 * (which is a no-op if the role already has the permission).
 *
 * This backfills the permission on environments where the catalog migration
 * 2026_03_13_000000_seed_catalog_data.php has already run and won't re-execute.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = SpatiePermission::firstOrCreate(
            ['name' => Permission::MANAGE_CATALOGS->value, 'guard_name' => 'web'],
        );

        $adminRole = SpatieRole::where('name', Role::ADMIN->value)
            ->where('guard_name', 'web')
            ->first();

        if ($adminRole) {
            $adminRole->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        // Permission management should not be rolled back.
    }
};
