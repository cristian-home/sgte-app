export type Timestamps = {
    created_at: string;
    updated_at: string;
};

export type SoftDeletes = {
    deleted_at: string | null;
};

export type DocumentType = {
    id: number;
    code: string;
    name: string;
    is_natural_person: boolean;
    is_legal_person: boolean;
} & Timestamps &
    SoftDeletes;

export type Eps = {
    id: number;
    code: string;
    name: string;
} & Timestamps &
    SoftDeletes;

export type PensionFund = {
    id: number;
    code: string;
    name: string;
} & Timestamps &
    SoftDeletes;

export type SeveranceFund = {
    id: number;
    code: string;
    name: string;
} & Timestamps &
    SoftDeletes;

export type ThirdParty = {
    id: number;
    document_type_id: number;
    identification_number: string;
    is_natural_person: boolean;
    first_name: string | null;
    second_name: string | null;
    first_lastname: string | null;
    second_lastname: string | null;
    company_name: string | null;
    trade_name: string | null;
    city: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    is_customer: boolean;
    is_provider: boolean;
    active: boolean;
    document_type?: DocumentType;
    vehicles?: Vehicle[];
    contracts?: Contract[];
} & Timestamps &
    SoftDeletes;

export type Vehicle = {
    id: number;
    internal_code: string;
    plate: string;
    mobile_number: string | null;
    brand: string;
    line: string;
    model_year: number;
    type: string;
    engine_number: string | null;
    chassis_number: string | null;
    capacity: number;
    city: string;
    is_third_party: boolean;
    third_party_id: number | null;
    soat_due_date: string | null;
    rtm_due_date: string | null;
    operation_card_due_date: string | null;
    status: string;
    third_party?: ThirdParty;
    services?: Service[];
} & Timestamps &
    SoftDeletes;

export type Driver = {
    id: number;
    document_type_id: number;
    identification_number: string;
    first_name: string;
    second_name: string | null;
    first_lastname: string;
    second_lastname: string | null;
    city: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    license_category: string;
    license_due_date: string | null;
    eps_id: number | null;
    pension_fund_id: number | null;
    severance_fund_id: number | null;
    has_social_security: boolean;
    active: boolean;
    document_type?: DocumentType;
    eps?: Eps;
    pension_fund?: PensionFund;
    severance_fund?: SeveranceFund;
} & Timestamps &
    SoftDeletes;

export type Contract = {
    id: number;
    contract_number: string;
    third_party_id: number;
    contract_object: string;
    start_date: string;
    end_date: string;
    route_description: string | null;
    is_generic: boolean;
    active: boolean;
    third_party?: ThirdParty;
    services?: Service[];
} & Timestamps &
    SoftDeletes;

export type Invoice = {
    id: number;
    invoice_number: string;
    total_value: string;
    issue_date: string;
    payment_status: string;
    notes: string | null;
    services?: Service[];
} & Timestamps &
    SoftDeletes;

export type Service = {
    id: number;
    contract_id: number;
    vehicle_id: number;
    driver_id: number | null;
    invoice_id: number | null;
    service_date: string;
    origin: string;
    destination: string;
    planned_start_time: string;
    planned_duration: number;
    actual_start_time: string | null;
    actual_end_time: string | null;
    unit_value: string;
    quantity: number;
    billing_group: string | null;
    payment_method: string;
    service_status: string;
    contract?: Contract;
    vehicle?: Vehicle;
    driver?: Driver;
    invoice?: Invoice;
} & Timestamps &
    SoftDeletes;

export type DayStatus = {
    id: number;
    date: string;
    status: string;
    executor_id: number | null;
    executed_at: string | null;
} & Timestamps;

export type ServiceIncident = {
    id: number;
    service_id: number;
    incident_type: string;
    description: string;
    registrar_id: number | null;
    is_driver_report: boolean;
    reported_at: string | null;
    affects_billing: boolean;
    additional_value: string | null;
    service?: Service;
} & Timestamps;

export type Fuec = {
    id: number;
    service_id: number;
    consecutive_number: number;
    generated_at: string | null;
    qr_code: string | null;
    status: string;
    pdf_url: string | null;
    service?: Service;
} & Timestamps;

export type VehicleLocation = {
    id: number;
    vehicle_id: number;
    recorded_at: string | null;
    latitude: string;
    longitude: string;
    is_manual: boolean;
    vehicle?: Vehicle;
} & Timestamps;
