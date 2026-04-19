<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Documentos próximos a vencer</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1f2937; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 16px; }
        p { line-height: 1.55; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px 12px; text-align: left; font-size: 14px; }
        th { background: #f3f4f6; font-weight: 600; }
        tr.urgent { background: #fee2e2; }
        tr.warning { background: #fef3c7; }
        .footer { margin-top: 24px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>Documentos próximos a vencer</h1>

    <p>Estimado(a) <strong>{{ $providerLabel }}</strong>:</p>

    <p>
        Algunos documentos de los vehículos que usted provee a SGTE están próximos
        a vencerse. Le solicitamos compartir las versiones actualizadas con nuestro
        equipo de operaciones antes de la fecha indicada para evitar la suspensión
        automática de la asignación de servicios.
    </p>

    <table>
        <thead>
            <tr>
                <th>Placa</th>
                <th>Documento</th>
                <th>Fecha de vencimiento</th>
                <th>Días restantes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                @php
                    $rowClass = $entry['days_until_expiry'] <= 1
                        ? 'urgent'
                        : ($entry['days_until_expiry'] <= 7 ? 'warning' : '');
                @endphp
                <tr class="{{ $rowClass }}">
                    <td style="font-family: monospace;">{{ $entry['plate'] }}</td>
                    <td>{{ $entry['document_label'] }}</td>
                    <td>{{ $entry['due_date'] }}</td>
                    <td>{{ $entry['days_until_expiry'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="footer">
        Este mensaje se envía automáticamente desde el Sistema de Gestión de
        Transporte Especial (SGTE). Para cualquier consulta, responda a este correo
        o escriba al equipo de operaciones.
    </p>
</body>
</html>
