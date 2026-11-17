@extends('application-print')
@section('title', 'Fólder B · Currículo descriptivo e índice')

@section('content')
    @unless ($application->submitted_at)
        <p><strong>Borrador.</strong> El índice se genera con los datos guardados.</p>
    @endunless
    <p>{{ $applicant['name'] }} · DNI {{ $applicant['document_number'] }}</p>
    <p>{{ $application->recruitmentCall->title }} · {{ $application->vacancy->school }} · AIRHSP {{ $application->vacancy->airhsp }}</p>
    <small>Expediente {{ $application->reference }}</small>

    <h2>Sustentos del perfil</h2>
    <table>
        <thead><tr><th>Requisito</th><th>Archivo</th></tr></thead>
        <tbody>
            @forelse (collect($requirements)->where('section', 'curriculo') as $key => $requirement)
                <tr>
                    <td>{{ $requirement['label'] }}</td>
                    <td>{{ $documents[$key]->name ?? 'Sin adjuntar' }}</td>
                </tr>
            @empty
                <tr><td colspan="2">Sin requisitos adicionales del perfil en esta sección.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Méritos y sustentos por rubro</h2>
    <table>
        <thead><tr><th>Rubro y criterio</th><th>Mérito declarado</th><th>Sustento vinculado</th></tr></thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry['rubric'] }}<br><small>{{ $entry['criterion'] }}</small></td>
                    <td>{{ $entry['description'] }}<br><small>Cantidad {{ $entry['quantity'] }}</small></td>
                    <td>{{ $documents[$entry['document_key']]->name ?? 'Sin adjuntar' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Sin méritos registrados en el formulario.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
