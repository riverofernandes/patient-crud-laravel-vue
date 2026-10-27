<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Receita de óculos</title>
    <style>
        /* DejaVu Sans ships with DomPDF and covers pt-BR accents, "−" (U+2212) and "°". */
        @page { margin: 2.2cm 2cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #1f2933; }
        h1 { font-size: 20pt; font-weight: bold; margin: 0 0 4pt; letter-spacing: 0.5pt; }
        .subtitle { color: #52606d; font-size: 9.5pt; margin: 0; }
        .header { border-bottom: 2px solid #1f2933; padding-bottom: 10pt; margin-bottom: 18pt; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 20pt; }
        .info td { padding: 3pt 0; vertical-align: top; }
        .info .label { color: #52606d; width: 32%; }
        .rx { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
        .rx th, .rx td { border: 1px solid #9aa5b1; padding: 7pt 6pt; text-align: center; }
        .rx th { background: #e4e7eb; font-size: 10pt; }
        .rx td.eye { text-align: left; font-weight: bold; }
        .note { color: #52606d; font-size: 9pt; }
        .signature { margin-top: 70pt; text-align: center; }
        .signature .line { border-top: 1px solid #1f2933; width: 60%; margin: 0 auto 4pt; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Receita de óculos</h1>
        <p class="subtitle">Prescrição de lentes corretivas</p>
    </div>

    <table class="info">
        <tr>
            <td class="label">Paciente</td>
            <td><strong>{{ $patientName }}</strong></td>
        </tr>
        <tr>
            <td class="label">Data de nascimento</td>
            <td>{{ $birthDate ?? '—' }}@if ($age !== null) ({{ $age }} {{ $age === 1 ? 'ano' : 'anos' }})@endif</td>
        </tr>
        <tr>
            <td class="label">Data</td>
            <td>{{ $date }}</td>
        </tr>
    </table>

    <table class="rx">
        <thead>
            <tr>
                <th></th>
                <th>Esférico</th>
                <th>Cilíndrico</th>
                <th>Eixo</th>
                <th>Adição</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="eye">{{ $row['label'] }}</td>
                    <td>{{ $row['spherical'] }}</td>
                    <td>{{ $row['cylindrical'] }}</td>
                    <td>{{ $row['axis'] }}</td>
                    <td>{{ $row['addition'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="note">Valores em dioptrias. Eixo em graus.</p>

    <div class="signature">
        <div class="line"></div>
        <div><strong>{{ $professionalName }}</strong></div>
        @if ($professionalCrm)
            <div>{{ $professionalCrm }}</div>
        @endif
    </div>
</body>
</html>
