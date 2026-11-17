@extends('application-print')
@section('title', 'Solicitud de inscripción')

@section('content')
    <p>Señor(a) Decano(a) de la Facultad de {{ $application->vacancy->faculty }}</p>
    <p>
        Yo, {{ $applicant['name'] }}, con DNI {{ $applicant['document_number'] }},
        solicito mi inscripción en {{ $application->recruitmentCall->title }}
        para la plaza de la Escuela Profesional de {{ $application->vacancy->school }}.
    </p>
    <table>
        <tr><th>Plaza (AIRHSP)</th><td>{{ $application->vacancy->airhsp }}</td></tr>
        <tr><th>Categoría</th><td>{{ $application->vacancy->category }}</td></tr>
        <tr><th>Carga horaria</th><td>{{ $application->vacancy->weekly_hours }} horas semanales</td></tr>
        <tr><th>Correo</th><td>{{ $applicant['email'] }}</td></tr>
        <tr><th>Teléfono</th><td>{{ $applicant['phone'] }}</td></tr>
    </table>
    <p>Fecha: ____ / ____ / ________</p>
    <p class="signature">Firma del postulante: ____________________________________</p>
@endsection
