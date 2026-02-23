<?php

namespace App\Enums;

enum Permission: string
{
    // Vehicle management
    case MANAGE_VEHICLES = 'manage.vehicles';
    case MANAGE_DRIVERS = 'manage.drivers';
    case MANAGE_CONTRACTS = 'manage.contracts';

    // Services
    case CREATE_SERVICES = 'create.services';
    case EDIT_PROJECTED_SERVICES = 'edit.projected.services';
    case EDIT_EXECUTED_SERVICES = 'edit.executed.services';
    case GENERATE_FUEC = 'generate.fuec';
    case EXECUTE_DAY = 'execute.day';

    // Reports and invoices
    case VIEW_REPORTS = 'view.reports';
    case VIEW_COMPLETED_SERVICES = 'view.completed.services';
    case GENERATE_INVOICES = 'generate.invoices';
    case ASSIGN_SERVICES_TO_INVOICES = 'assign.services.to.invoices';

    // Driver specific
    case REGISTER_TIMES_AND_INCIDENTS = 'register.times.incidents';

    // Common
    case RECEIVE_NOTIFICATIONS = 'receive.notifications';

    public function label(): string
    {
        return match ($this) {
            self::MANAGE_VEHICLES => 'Gestionar vehículos',
            self::MANAGE_DRIVERS => 'Gestionar conductores',
            self::MANAGE_CONTRACTS => 'Gestionar contratos',
            self::CREATE_SERVICES => 'Crear servicios',
            self::EDIT_PROJECTED_SERVICES => 'Editar servicios (proyectados)',
            self::EDIT_EXECUTED_SERVICES => 'Editar servicios (ejecutados)',
            self::GENERATE_FUEC => 'Generar FUEC',
            self::EXECUTE_DAY => 'Ejecutar día',
            self::VIEW_REPORTS => 'Ver reportes',
            self::VIEW_COMPLETED_SERVICES => 'Ver servicios finalizados',
            self::GENERATE_INVOICES => 'Generar facturas',
            self::ASSIGN_SERVICES_TO_INVOICES => 'Asociar servicios a facturas',
            self::REGISTER_TIMES_AND_INCIDENTS => 'Registrar tiempos reales y novedades',
            self::RECEIVE_NOTIFICATIONS => 'Recibir notificaciones',
        };
    }
}
