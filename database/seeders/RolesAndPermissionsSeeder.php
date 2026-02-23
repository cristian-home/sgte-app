<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        foreach (Permission::cases() as $permission) {
            SpatiePermission::firstOrCreate(
                ['name' => $permission->value, 'guard_name' => 'web'],
            );
        }

        // Create roles and assign permissions
        $this->createAdminRole();
        $this->createOperatorRole();
        $this->createDriverRole();
        $this->createAccountingRole();
    }

    private function createAdminRole(): void
    {
        $role = SpatieRole::firstOrCreate(
            ['name' => Role::ADMIN->value, 'guard_name' => 'web'],
        );

        $permissions = [
            Permission::MANAGE_VEHICLES,
            Permission::MANAGE_DRIVERS,
            Permission::MANAGE_CONTRACTS,
            Permission::CREATE_SERVICES,
            Permission::EDIT_PROJECTED_SERVICES,
            Permission::EDIT_EXECUTED_SERVICES,
            Permission::GENERATE_FUEC,
            Permission::EXECUTE_DAY,
            Permission::VIEW_REPORTS,
            Permission::VIEW_COMPLETED_SERVICES,
            Permission::GENERATE_INVOICES,
            Permission::ASSIGN_SERVICES_TO_INVOICES,
            Permission::RECEIVE_NOTIFICATIONS,
        ];

        $role->givePermissionTo(array_map(fn ($p) => $p->value, $permissions));
    }

    private function createOperatorRole(): void
    {
        $role = SpatieRole::firstOrCreate(
            ['name' => Role::OPERATOR->value, 'guard_name' => 'web'],
        );

        $permissions = [
            Permission::CREATE_SERVICES,
            Permission::EDIT_PROJECTED_SERVICES,
            Permission::GENERATE_FUEC,
            Permission::EXECUTE_DAY,
            Permission::VIEW_REPORTS,
            Permission::RECEIVE_NOTIFICATIONS,
        ];

        $role->givePermissionTo(array_map(fn ($p) => $p->value, $permissions));
    }

    private function createDriverRole(): void
    {
        $role = SpatieRole::firstOrCreate(
            ['name' => Role::DRIVER->value, 'guard_name' => 'web'],
        );

        $permissions = [
            Permission::REGISTER_TIMES_AND_INCIDENTS,
            Permission::RECEIVE_NOTIFICATIONS,
        ];

        $role->givePermissionTo(array_map(fn ($p) => $p->value, $permissions));
    }

    private function createAccountingRole(): void
    {
        $role = SpatieRole::firstOrCreate(
            ['name' => Role::ACCOUNTING->value, 'guard_name' => 'web'],
        );

        $permissions = [
            Permission::EDIT_EXECUTED_SERVICES,
            Permission::VIEW_REPORTS,
            Permission::VIEW_COMPLETED_SERVICES,
            Permission::GENERATE_INVOICES,
            Permission::ASSIGN_SERVICES_TO_INVOICES,
            Permission::RECEIVE_NOTIFICATIONS,
        ];

        $role->givePermissionTo(array_map(fn ($p) => $p->value, $permissions));
    }
}
