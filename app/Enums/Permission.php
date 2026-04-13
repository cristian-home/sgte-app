<?php

namespace App\Enums;

enum Permission: string
{
    // Dashboard & Settings
    case VIEW_DASHBOARD = 'dashboard.view';
    case VIEW_SETTINGS = 'settings.view';

    // Vehicles
    case VIEW_VEHICLES = 'vehicles.view';
    case CREATE_VEHICLES = 'vehicles.create';
    case UPDATE_VEHICLES = 'vehicles.update';
    case DELETE_VEHICLES = 'vehicles.delete';

    // Drivers
    case VIEW_DRIVERS = 'drivers.view';
    case CREATE_DRIVERS = 'drivers.create';
    case UPDATE_DRIVERS = 'drivers.update';
    case DELETE_DRIVERS = 'drivers.delete';

    // Third Parties
    case VIEW_THIRD_PARTIES = 'third-parties.view';
    case CREATE_THIRD_PARTIES = 'third-parties.create';
    case UPDATE_THIRD_PARTIES = 'third-parties.update';
    case DELETE_THIRD_PARTIES = 'third-parties.delete';

    // Contracts
    case VIEW_CONTRACTS = 'contracts.view';
    case CREATE_CONTRACTS = 'contracts.create';
    case UPDATE_CONTRACTS = 'contracts.update';
    case DELETE_CONTRACTS = 'contracts.delete';

    // Services
    case VIEW_SERVICES = 'services.view';
    case CREATE_SERVICES = 'services.create';
    case UPDATE_PROJECTED_SERVICES = 'services.update-projected';
    case UPDATE_EXECUTED_SERVICES = 'services.update-executed';
    case DELETE_SERVICES = 'services.delete';
    case REGISTER_SERVICE_TIMES = 'services.register-times';

    // Day Summary
    case VIEW_DAY_SUMMARY = 'day-summary.view';
    case EXECUTE_DAY = 'day-summary.execute';

    // Incidents
    case VIEW_INCIDENTS = 'incidents.view';
    case CREATE_INCIDENTS = 'incidents.create';
    case UPDATE_INCIDENTS = 'incidents.update';
    case DELETE_INCIDENTS = 'incidents.delete';

    // Invoices
    case VIEW_INVOICES = 'invoices.view';
    case CREATE_INVOICES = 'invoices.create';
    case UPDATE_INVOICES = 'invoices.update';
    case DELETE_INVOICES = 'invoices.delete';
    case ASSIGN_SERVICES_TO_INVOICES = 'invoices.assign-services';

    // Reports
    case VIEW_REPORTS = 'reports.view';

    // FUEC (optional)
    case VIEW_FUEC = 'fuec.view';
    case GENERATE_FUEC = 'fuec.generate';

    // Users
    case VIEW_USERS = 'users.view';
    case CREATE_USERS = 'users.create';
    case UPDATE_USERS = 'users.update';
    case DELETE_USERS = 'users.delete';

    // Incident Types (catalog)
    case VIEW_INCIDENT_TYPES = 'incident-types.view';
    case CREATE_INCIDENT_TYPES = 'incident-types.create';
    case UPDATE_INCIDENT_TYPES = 'incident-types.update';
    case DELETE_INCIDENT_TYPES = 'incident-types.delete';

    // Static Catalogs (document types, EPS, pension funds, severance funds)
    case MANAGE_CATALOGS = 'catalogs.manage';

    // Audit log (Administración)
    case VIEW_AUDIT_LOG = 'audit-log.view';

    // Notifications
    case RECEIVE_NOTIFICATIONS = 'notifications.receive';

    public function label(): string
    {
        return match ($this) {
            self::VIEW_DASHBOARD => 'Ver dashboard',
            self::VIEW_SETTINGS => 'Acceder a configuración',
            self::VIEW_VEHICLES => 'Ver vehículos',
            self::CREATE_VEHICLES => 'Crear vehículos',
            self::UPDATE_VEHICLES => 'Editar vehículos',
            self::DELETE_VEHICLES => 'Eliminar vehículos',
            self::VIEW_DRIVERS => 'Ver conductores',
            self::CREATE_DRIVERS => 'Crear conductores',
            self::UPDATE_DRIVERS => 'Editar conductores',
            self::DELETE_DRIVERS => 'Eliminar conductores',
            self::VIEW_THIRD_PARTIES => 'Ver terceros',
            self::CREATE_THIRD_PARTIES => 'Crear terceros',
            self::UPDATE_THIRD_PARTIES => 'Editar terceros',
            self::DELETE_THIRD_PARTIES => 'Eliminar terceros',
            self::VIEW_CONTRACTS => 'Ver contratos',
            self::CREATE_CONTRACTS => 'Crear contratos',
            self::UPDATE_CONTRACTS => 'Editar contratos',
            self::DELETE_CONTRACTS => 'Eliminar contratos',
            self::VIEW_SERVICES => 'Ver servicios',
            self::CREATE_SERVICES => 'Crear servicios',
            self::UPDATE_PROJECTED_SERVICES => 'Editar servicios proyectados',
            self::UPDATE_EXECUTED_SERVICES => 'Editar servicios ejecutados',
            self::DELETE_SERVICES => 'Eliminar servicios',
            self::REGISTER_SERVICE_TIMES => 'Registrar tiempos reales',
            self::VIEW_DAY_SUMMARY => 'Ver resumen del día',
            self::EXECUTE_DAY => 'Ejecutar día',
            self::VIEW_INCIDENTS => 'Ver novedades',
            self::CREATE_INCIDENTS => 'Registrar novedades',
            self::UPDATE_INCIDENTS => 'Editar novedades',
            self::DELETE_INCIDENTS => 'Eliminar novedades',
            self::VIEW_INVOICES => 'Ver facturas',
            self::CREATE_INVOICES => 'Crear facturas',
            self::UPDATE_INVOICES => 'Editar facturas',
            self::DELETE_INVOICES => 'Eliminar facturas',
            self::ASSIGN_SERVICES_TO_INVOICES => 'Asociar servicios a facturas',
            self::VIEW_REPORTS => 'Ver reportes',
            self::VIEW_FUEC => 'Ver FUEC',
            self::GENERATE_FUEC => 'Generar FUEC',
            self::VIEW_USERS => 'Ver usuarios',
            self::CREATE_USERS => 'Crear usuarios',
            self::UPDATE_USERS => 'Editar usuarios',
            self::DELETE_USERS => 'Eliminar usuarios',
            self::VIEW_INCIDENT_TYPES => 'Ver tipos de novedad',
            self::CREATE_INCIDENT_TYPES => 'Crear tipos de novedad',
            self::UPDATE_INCIDENT_TYPES => 'Editar tipos de novedad',
            self::DELETE_INCIDENT_TYPES => 'Eliminar tipos de novedad',
            self::MANAGE_CATALOGS => 'Gestionar catálogos (documentos, EPS, fondos)',
            self::VIEW_AUDIT_LOG => 'Ver registro de auditoría',
            self::RECEIVE_NOTIFICATIONS => 'Recibir notificaciones',
        };
    }
}
