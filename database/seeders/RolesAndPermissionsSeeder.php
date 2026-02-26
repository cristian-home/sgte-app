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
        $this->createSuperAdminRole();
        $this->createAdminRole();
        $this->createOperatorRole();
        $this->createDriverRole();
        $this->createAccountingRole();
    }

    private function createSuperAdminRole(): void
    {
        SpatieRole::firstOrCreate(
            ['name' => Role::SUPER_ADMIN->value, 'guard_name' => 'web'],
        );
    }

    private function createAdminRole(): void
    {
        $role = SpatieRole::firstOrCreate(
            ['name' => Role::ADMIN->value, 'guard_name' => 'web'],
        );

        $permissions = [
            // Dashboard & Settings
            Permission::VIEW_DASHBOARD,
            Permission::VIEW_SETTINGS,

            // Vehicles
            Permission::VIEW_VEHICLES,
            Permission::CREATE_VEHICLES,
            Permission::UPDATE_VEHICLES,
            Permission::DELETE_VEHICLES,

            // Drivers
            Permission::VIEW_DRIVERS,
            Permission::CREATE_DRIVERS,
            Permission::UPDATE_DRIVERS,
            Permission::DELETE_DRIVERS,

            // Third Parties
            Permission::VIEW_THIRD_PARTIES,
            Permission::CREATE_THIRD_PARTIES,
            Permission::UPDATE_THIRD_PARTIES,
            Permission::DELETE_THIRD_PARTIES,

            // Contracts
            Permission::VIEW_CONTRACTS,
            Permission::CREATE_CONTRACTS,
            Permission::UPDATE_CONTRACTS,
            Permission::DELETE_CONTRACTS,

            // Services
            Permission::VIEW_SERVICES,
            Permission::CREATE_SERVICES,
            Permission::UPDATE_PROJECTED_SERVICES,
            Permission::UPDATE_EXECUTED_SERVICES,
            Permission::DELETE_SERVICES,

            // Day Summary
            Permission::VIEW_DAY_SUMMARY,
            Permission::EXECUTE_DAY,

            // Incidents
            Permission::VIEW_INCIDENTS,
            Permission::CREATE_INCIDENTS,
            Permission::UPDATE_INCIDENTS,
            Permission::DELETE_INCIDENTS,

            // Invoices
            Permission::VIEW_INVOICES,
            Permission::CREATE_INVOICES,
            Permission::UPDATE_INVOICES,
            Permission::DELETE_INVOICES,
            Permission::ASSIGN_SERVICES_TO_INVOICES,

            // Reports
            Permission::VIEW_REPORTS,

            // FUEC
            Permission::VIEW_FUEC,
            Permission::GENERATE_FUEC,

            // Users
            Permission::VIEW_USERS,
            Permission::CREATE_USERS,
            Permission::UPDATE_USERS,
            Permission::DELETE_USERS,

            // Notifications
            Permission::RECEIVE_NOTIFICATIONS,
        ];

        $role->syncPermissions(array_map(fn ($p) => $p->value, $permissions));
    }

    private function createOperatorRole(): void
    {
        $role = SpatieRole::firstOrCreate(
            ['name' => Role::OPERATOR->value, 'guard_name' => 'web'],
        );

        $permissions = [
            // Dashboard & Settings
            Permission::VIEW_DASHBOARD,
            Permission::VIEW_SETTINGS,

            // Vehicles (read-only)
            Permission::VIEW_VEHICLES,

            // Drivers (read-only)
            Permission::VIEW_DRIVERS,

            // Third Parties (read-only)
            Permission::VIEW_THIRD_PARTIES,

            // Contracts (read-only)
            Permission::VIEW_CONTRACTS,

            // Services
            Permission::VIEW_SERVICES,
            Permission::CREATE_SERVICES,
            Permission::UPDATE_PROJECTED_SERVICES,

            // Day Summary
            Permission::VIEW_DAY_SUMMARY,
            Permission::EXECUTE_DAY,

            // Incidents
            Permission::VIEW_INCIDENTS,
            Permission::CREATE_INCIDENTS,

            // Reports
            Permission::VIEW_REPORTS,

            // FUEC
            Permission::VIEW_FUEC,
            Permission::GENERATE_FUEC,

            // Notifications
            Permission::RECEIVE_NOTIFICATIONS,
        ];

        $role->syncPermissions(array_map(fn ($p) => $p->value, $permissions));
    }

    private function createDriverRole(): void
    {
        $role = SpatieRole::firstOrCreate(
            ['name' => Role::DRIVER->value, 'guard_name' => 'web'],
        );

        $permissions = [
            // Dashboard & Settings
            Permission::VIEW_DASHBOARD,
            Permission::VIEW_SETTINGS,

            // Services (own only)
            Permission::VIEW_SERVICES,
            Permission::REGISTER_SERVICE_TIMES,

            // Incidents
            Permission::VIEW_INCIDENTS,
            Permission::CREATE_INCIDENTS,

            // Notifications
            Permission::RECEIVE_NOTIFICATIONS,
        ];

        $role->syncPermissions(array_map(fn ($p) => $p->value, $permissions));
    }

    private function createAccountingRole(): void
    {
        $role = SpatieRole::firstOrCreate(
            ['name' => Role::ACCOUNTING->value, 'guard_name' => 'web'],
        );

        $permissions = [
            // Dashboard & Settings
            Permission::VIEW_DASHBOARD,
            Permission::VIEW_SETTINGS,

            // Third Parties (read-only)
            Permission::VIEW_THIRD_PARTIES,

            // Contracts (read-only)
            Permission::VIEW_CONTRACTS,

            // Services
            Permission::VIEW_SERVICES,
            Permission::UPDATE_EXECUTED_SERVICES,

            // Day Summary (read-only)
            Permission::VIEW_DAY_SUMMARY,

            // Incidents (read-only)
            Permission::VIEW_INCIDENTS,

            // Invoices
            Permission::VIEW_INVOICES,
            Permission::CREATE_INVOICES,
            Permission::UPDATE_INVOICES,
            Permission::ASSIGN_SERVICES_TO_INVOICES,

            // Reports
            Permission::VIEW_REPORTS,

            // Notifications
            Permission::RECEIVE_NOTIFICATIONS,
        ];

        $role->syncPermissions(array_map(fn ($p) => $p->value, $permissions));
    }
}
