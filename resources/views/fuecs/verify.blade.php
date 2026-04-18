@php
    use App\Enums\FuecStatus;
    use Illuminate\Support\Carbon;

    $isActive = $fuec->status === FuecStatus::Active;
    $service = $fuec->service;
    $contract = $service?->contract;
    $thirdParty = $contract?->thirdParty;
    $vehicle = $service?->vehicle;
    $driver = $service?->driver;
    $range = $fuec->fuecNumberRange;

    $customerName = $thirdParty?->is_natural_person
        ? trim(($thirdParty->first_name ?? '').' '.($thirdParty->first_lastname ?? ''))
        : ($thirdParty?->company_name ?? '—');
    $driverFullName = $driver
        ? trim(($driver->first_name ?? '').' '.($driver->first_lastname ?? ''))
        : '—';

    $fmtDate = fn ($d) => $d instanceof \DateTimeInterface
        ? Carbon::instance($d)->locale('es_CO')->isoFormat('DD [de] MMMM [de] YYYY')
        : ($d ? Carbon::parse($d)->locale('es_CO')->isoFormat('DD [de] MMMM [de] YYYY') : '—');

    $fmtDateTime = fn ($d) => $d instanceof \DateTimeInterface
        ? Carbon::instance($d)->locale('es_CO')->isoFormat('DD/MM/YYYY HH:mm')
        : ($d ? Carbon::parse($d)->locale('es_CO')->isoFormat('DD/MM/YYYY HH:mm') : '—');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación FUEC Nº {{ $fuec->consecutive_number }} — SGTE</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 16px;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #f9fafb;
            color: #111827;
            line-height: 1.5;
        }
        .wrap {
            max-width: 640px;
            margin: 0 auto;
        }
        .brand {
            text-align: center;
            font-size: 13px;
            color: #6b7280;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .status-badge {
            display: block;
            text-align: center;
            padding: 24px 16px;
            border-radius: 12px;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 3px;
        }
        .status-badge.vigente {
            background: #dcfce7;
            color: #14532d;
            border: 3px solid #16a34a;
        }
        .status-badge.anulado {
            background: #fee2e2;
            color: #7f1d1d;
            border: 3px solid #dc2626;
        }
        .meta {
            text-align: center;
            margin-top: 8px;
            color: #4b5563;
            font-size: 13px;
        }
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-top: 16px;
        }
        .card h2 {
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .row {
            display: table;
            width: 100%;
            padding: 6px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .row:last-child { border-bottom: 0; }
        .label {
            display: table-cell;
            width: 40%;
            color: #6b7280;
            font-weight: 500;
            font-size: 13px;
        }
        .value {
            display: table-cell;
            color: #111827;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            margin-top: 24px;
            color: #9ca3af;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">SGTE · Sistema de Gestión de Transporte Especial</div>

    <div class="status-badge {{ $isActive ? 'vigente' : 'anulado' }}">
        @if ($isActive)
            VIGENTE
        @else
            ANULADO
        @endif
    </div>

    <div class="meta">
        FUEC Nº <strong>{{ $fuec->consecutive_number }}</strong>
        &middot; Resolución {{ $range?->resolution_number ?? '—' }} de {{ $range?->resolution_year ?? '—' }}<br>
        Emitido el {{ $fmtDateTime($fuec->generated_at) }}
    </div>

    <div class="card">
        <h2>Contrato</h2>
        <div class="row"><span class="label">Número</span><span class="value">{{ $contract?->contract_number ?? '—' }}</span></div>
        <div class="row"><span class="label">Cliente</span><span class="value">{{ $customerName }}</span></div>
    </div>

    <div class="card">
        <h2>Vehículo y Conductor</h2>
        <div class="row"><span class="label">Placa</span><span class="value">{{ $vehicle?->plate ?? '—' }}</span></div>
        <div class="row"><span class="label">Conductor</span><span class="value">{{ $driverFullName }}</span></div>
    </div>

    <div class="card">
        <h2>Servicio</h2>
        <div class="row"><span class="label">Fecha</span><span class="value">{{ $fmtDate($service?->service_date) }}</span></div>
        <div class="row"><span class="label">Origen</span><span class="value">{{ $service?->originMunicipality?->name ?? '—' }}</span></div>
        <div class="row"><span class="label">Destino</span><span class="value">{{ $service?->destinationMunicipality?->name ?? '—' }}</span></div>
    </div>

    @if (! $isActive)
        <div class="card" style="background: #fef2f2; border-color: #fecaca;">
            <h2 style="color:#991b1b;">Documento Anulado</h2>
            <p style="margin:0; color:#7f1d1d; font-size:14px;">
                Este FUEC fue anulado posteriormente a su emisión y <strong>no es válido</strong>
                como soporte del servicio. Para verificar su estado actual, consulte con la
                empresa prestadora del servicio.
            </p>
        </div>
    @endif

    <div class="footer">
        Este registro es público y no requiere autenticación. Para ver el PDF completo,
        contacte al administrador del sistema.
    </div>
</div>
</body>
</html>
