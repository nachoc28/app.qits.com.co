<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lead {{ $lead->id }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.45;
            margin: 24px;
        }

        h1 {
            margin: 0 0 8px 0;
            font-size: 20px;
            color: #111827;
        }

        .subtitle {
            margin-bottom: 18px;
            color: #4b5563;
            font-size: 11px;
        }

        .section {
            margin-top: 18px;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #111827;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #e5e7eb;
            padding: 7px;
            vertical-align: top;
        }

        th {
            width: 32%;
            background: #f3f4f6;
            text-align: left;
            font-weight: bold;
            color: #111827;
        }

        .message {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 10px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <h1>Detalle de Lead</h1>
    <div class="subtitle">Generado automáticamente por WhatsApp Hub - Módulo 1</div>

    <div class="section">
        <div class="section-title">Información principal</div>
        <table>
            <tr>
                <th>Nombre completo</th>
                <td>{{ $lead->full_name ?: 'N/A' }}</td>
            </tr>
            <tr>
                <th>Teléfono</th>
                <td>{{ $lead->phone ?: 'N/A' }}</td>
            </tr>
            <tr>
                <th>Correo</th>
                <td>{{ $lead->email ?: 'N/A' }}</td>
            </tr>
            <tr>
                <th>Empresa</th>
                <td>{{ $lead->company ?: 'N/A' }}</td>
            </tr>
            <tr>
                <th>Ciudad</th>
                <td>{{ $lead->city ?: 'N/A' }}</td>
            </tr>
            <tr>
                <th>Formulario</th>
                <td>{{ $lead->origin_form_name ?: 'N/A' }}</td>
            </tr>
            <tr>
                <th>URL de origen</th>
                <td>{{ $lead->origin_url ?: 'N/A' }}</td>
            </tr>
            <tr>
                <th>Fecha de envío</th>
                <td>{{ optional($lead->created_at)->format('Y-m-d H:i:s') ?: 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Mensaje</div>
        <div class="message">{{ $lead->message ?: 'N/A' }}</div>
    </div>

    @if(!empty($payloadRows))
        <div class="section">
            <div class="section-title">Datos adicionales del formulario</div>
            <table>
                @foreach($payloadRows as $row)
                    <tr>
                        <th>{{ $row['label'] }}</th>
                        <td>{{ $row['value'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</body>
</html>
