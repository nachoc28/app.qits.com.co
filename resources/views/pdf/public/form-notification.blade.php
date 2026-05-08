<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud formulario {{ $notificationId }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #111827;
            margin: 24px;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 6px 0;
        }

        .subtitle {
            font-size: 11px;
            color: #4b5563;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            width: 34%;
            background: #f3f4f6;
        }

        .section-title {
            font-weight: bold;
            margin: 16px 0 8px 0;
            font-size: 13px;
        }

        .muted {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <h1>Solicitud de formulario</h1>
    <div class="subtitle">Notificacion #{{ $notificationId }}</div>

    <table>
        <tbody>
            <tr>
                <th>Nombre</th>
                <td>{{ $mainData['nombre'] !== '' ? $mainData['nombre'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Apellido</th>
                <td>{{ $mainData['apellido'] !== '' ? $mainData['apellido'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Nombre completo</th>
                <td>{{ $mainData['nombre_completo'] !== '' ? $mainData['nombre_completo'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Telefono</th>
                <td>{{ $mainData['telefono'] !== '' ? $mainData['telefono'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Email</th>
                <td>{{ $mainData['email'] !== '' ? $mainData['email'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Servicio</th>
                <td>{{ $mainData['servicio'] !== '' ? $mainData['servicio'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Formulario</th>
                <td>{{ $mainData['form_name'] !== '' ? $mainData['form_name'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Page URL</th>
                <td>{{ $mainData['page_url'] !== '' ? $mainData['page_url'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Fecha de envio</th>
                <td>{{ $mainData['submitted_at'] !== '' ? $mainData['submitted_at'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Mensaje / comentario</th>
                <td>{{ $mainData['mensaje'] !== '' ? $mainData['mensaje'] : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Consentimiento</th>
                <td>
                    @if ($mainData['consentimiento'] === true)
                        Si
                    @elseif ($mainData['consentimiento'] === false)
                        No
                    @else
                        N/A
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Campos adicionales</div>

    @if (!empty($additionalFields))
        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($additionalFields as $label => $value)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ (string) $value !== '' ? $value : 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="muted">N/A</div>
    @endif
</body>
</html>
