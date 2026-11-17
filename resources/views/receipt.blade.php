@extends('application-print')
@section('title', 'Cargo de recepción de expediente')

@section('content')
    <p>{{ $application->recruitmentCall->title }}</p>
    <table>
        <tr><th>Código de expediente</th><td>{{ $application->reference }}</td></tr>
        <tr><th>Postulante</th><td>{{ $application->applicant_snapshot['name'] }}</td></tr>
        <tr><th>DNI</th><td>{{ $application->applicant_snapshot['document_number'] }}</td></tr>
        <tr><th>Plaza</th><td>{{ $application->vacancy->school }} · AIRHSP {{ $application->vacancy->airhsp }}</td></tr>
        <tr><th>Recepción (hora de Perú)</th><td>{{ $application->submitted_at->format('d/m/Y H:i:s') }}</td></tr>
    </table>
    @if ($application->screening_snapshot)
        <h2>Resumen de lo declarado</h2>
        <p>
            Experiencia computada: {{ $application->screening_snapshot['experience_days'] }} días.
            Capacitación: {{ $application->screening_snapshot['training_hours'] }} horas.
            @if ($application->recruitmentCall->evaluation_mode === 'nombramiento_referencia')
                La comisión revisará los sustentos y calificará los rubros externos e internos de la convocatoria.
            @else
                Puntaje preliminar: {{ $application->declared_score }}. La comisión verifica los sustentos.
            @endif
        </p>
    @endif
    <h2>Documentos recibidos</h2>
    <table>
        <thead><tr><th>Fólder y requisito</th><th>Archivo recibido</th></tr></thead>
        <tbody>
            @foreach ($documents as $document)
                @php($requirement = $requirements[$document->requirement_key] ?? null)
                <tr>
                    <td>
                        @if ($requirement)
                            <strong>{{ $requirement['section'] === 'administrativos' ? 'Fólder A' : 'Fólder B' }}</strong>
                            <br>{{ $requirement['label'] }}
                        @else
                            Documento del expediente
                        @endif
                    </td>
                    <td>{{ $document->name }}<br><small>{{ number_format($document->size / 1024, 1) }} KB</small></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <h2>Huella de integridad SHA-256</h2>
    <p class="hash">{{ $application->receipt_hash }}</p>
    <p>Este cargo acredita la recepción. La aptitud y la calificación están sujetas a revisión. La huella permite comprobar la integridad del expediente.</p>
@endsection
