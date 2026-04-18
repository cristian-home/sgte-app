/**
 * Shapes + constants for the audit log module (REQ-009).
 *
 * The projection on the server lives in `AuditLogController@index` —
 * keep the `ActivityRow` shape in lock-step with
 * `AuditLogController::projectActivity()`.
 */

/**
 * One row as projected by `AuditLogController@index`.
 */
export interface ActivityRow {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    /** Raw class string — e.g. "App\\Models\\Service". */
    subject_type: string | null;
    subject_id: number | null;
    causer: {
        id: number;
        name: string;
        email: string;
    } | null;
    /** ISO 8601 timestamp. */
    created_at: string | null;
    /**
     * Full properties bag as stored by spatie/laravel-activitylog.
     * Custom keys (e.g. `justification`, `edited_on_executed_day`)
     * sit next to spatie's `attributes` / `old` sub-keys.
     */
    properties: Record<string, unknown>;
    /** Convenience copy of `properties.attributes` for diff rendering. */
    attributes: Record<string, unknown>;
    /** Convenience copy of `properties.old` for diff rendering. */
    old_attributes: Record<string, unknown>;
}

/**
 * One entry in the backend-supplied `subjectTypes` filter option list.
 */
export interface SubjectTypeOption {
    /** Raw class string used as the filter value. */
    value: string;
    /** Human label (Spanish) for the Select option. */
    label: string;
}

/**
 * Map subject_type class strings to the list-page path prefix so the
 * Entidad cell in the audit log can Link to the real show page. Types
 * not in this map render as plain text (e.g. EPS / PensionFund —
 * their CRUD still renders as the Blueprint scaffold).
 */
export const SUBJECT_TYPE_LINK_MAP: Record<string, string> = {
    'App\\Models\\Service': '/services',
    'App\\Models\\Invoice': '/invoices',
    'App\\Models\\Contract': '/contracts',
    'App\\Models\\ServiceIncident': '/service-incidents',
    'App\\Models\\DayStatus': '/day-statuses',
    'App\\Models\\Vehicle': '/vehicles',
    'App\\Models\\Driver': '/drivers',
    'App\\Models\\ThirdParty': '/third-parties',
    'App\\Models\\User': '/users',
    'App\\Models\\Fuec': '/fuecs',
    'App\\Models\\VehicleLocation': '/vehicle-locations',
    'App\\Models\\IncidentType': '/incident-types',
};

/**
 * Fallback map used by the frontend when `subjectTypes` payload is
 * empty (e.g. an empty audit log on a fresh install) or when a row's
 * subject_type wasn't in the distinct-scan window used to compute the
 * filter options. Keeps the Entidad cell readable even in edge cases.
 */
export const SUBJECT_TYPE_FALLBACK_LABELS: Record<string, string> = {
    'App\\Models\\Service': 'Servicio',
    'App\\Models\\Invoice': 'Factura',
    'App\\Models\\Contract': 'Contrato',
    'App\\Models\\ServiceIncident': 'Novedad',
    'App\\Models\\DayStatus': 'Día',
    'App\\Models\\Vehicle': 'Vehículo',
    'App\\Models\\Driver': 'Conductor',
    'App\\Models\\ThirdParty': 'Tercero',
    'App\\Models\\User': 'Usuario',
    'App\\Models\\Fuec': 'FUEC',
    'App\\Models\\VehicleLocation': 'Ubicación',
    'App\\Models\\IncidentType': 'Tipo de Novedad',
    'App\\Models\\DocumentType': 'Tipo de Documento',
    'App\\Models\\Eps': 'EPS',
    'App\\Models\\PensionFund': 'Fondo de Pensiones',
    'App\\Models\\SeveranceFund': 'Fondo de Cesantías',
};

/**
 * Resolve a subject_type class string to its Spanish label. Tries the
 * backend-supplied `subjectTypes` list first (so the label stays in
 * lock-step with the server-side `SUBJECT_TYPE_LABELS` constant);
 * falls back to the static map above; last resort is the last path
 * segment of the class string.
 */
export function subjectTypeLabel(
    subjectType: string | null,
    options: SubjectTypeOption[],
): string {
    if (!subjectType) {
        return '—';
    }
    const fromOptions = options.find(
        (option) => option.value === subjectType,
    )?.label;
    if (fromOptions) {
        return fromOptions;
    }
    const fallback = SUBJECT_TYPE_FALLBACK_LABELS[subjectType];
    if (fallback) {
        return fallback;
    }
    const parts = subjectType.split('\\');
    return parts[parts.length - 1] ?? subjectType;
}
