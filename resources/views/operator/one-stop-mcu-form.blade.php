@extends('operator.layout')

@section('title', __('One Stop MCU examination'))

@section('content')
<section aria-labelledby="mcu-form-title">
    <h1 id="mcu-form-title">{{ __('One Stop MCU screening') }}</h1>
    <div class="card">
        <h2>{{ $member['name'] }}</h2>
        <p class="muted">{{ __('Date of birth') }}: {{ $member['birth_date'] }} · {{ __('Sex') }}: {{ $member['sex'] }} · MRN: {{ $member['medical_record_number'] }}</p>
        <p class="muted">{{ __('Site') }}: {{ $site_name }}</p>
        @if ($existing_exam)
            <p>{{ __('An MCU examination is already saved for this visit.') }}</p>
            <a class="primary-action" href="{{ route('operator.one-stop-mcu.pdf', $admission_id) }}" target="_blank" rel="noopener">{{ __('Print / view MCU PDF') }}</a>
            <a href="{{ route('operator.one-stop-mcu.pdf', ['admission' => $admission_id, 'download' => 1]) }}">{{ __('Download MCU PDF') }}</a>
        @else
            <form method="POST" action="{{ route('operator.one-stop-mcu.store', $admission_id) }}">
                @csrf
                <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                @error('mcu')<p class="error" role="alert">{{ $message }}</p>@enderror

                <label for="examined_at">{{ __('Examination date and time') }}</label>
                <input id="examined_at" name="examined_at" type="datetime-local" value="{{ old('examined_at', now($site_timezone)->format('Y-m-d\TH:i')) }}" required>
                @error('examined_at')<p class="error">{{ $message }}</p>@enderror

                <div class="grid">
                    @foreach ([
                        ['systolic_bp', __('Systolic blood pressure'), 'mmHg'],
                        ['diastolic_bp', __('Diastolic blood pressure'), 'mmHg'],
                        ['weight_kg', __('Weight'), 'kg'],
                        ['height_cm', __('Height'), 'cm'],
                        ['temperature_c', __('Temperature'), '°C'],
                        ['glucose_mg_dl', __('GCU glucose'), 'mg/dL'],
                        ['total_cholesterol_mg_dl', __('Total cholesterol'), 'mg/dL'],
                        ['uric_acid_mg_dl', __('Uric acid'), 'mg/dL'],
                    ] as [$field, $label, $unit])
                        <div>
                            <label for="{{ $field }}">{{ $label }} ({{ $unit }})</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="number" step="any" min="{{ in_array($field, ['glucose_mg_dl', 'total_cholesterol_mg_dl', 'uric_acid_mg_dl'], true) ? '0' : '0.01' }}" value="{{ old($field) }}" required>
                            @error($field)<p class="error">{{ $message }}</p>@enderror
                            @if ($field === 'height_cm')<p class="muted">{{ __('Alat ukur tinggi badan: Microtoise') }}</p>@endif
                        </div>
                    @endforeach
                </div>

                <h2>{{ __('GCU fasting context') }}</h2>
                <label for="fasting_status">{{ __('Fasting status') }}</label>
                <select id="fasting_status" name="fasting_status" required>
                    <option value="">{{ __('Select status') }}</option>
                    <option value="fasting" @selected(old('fasting_status') === 'fasting')>{{ __('Fasting') }}</option>
                    <option value="non_fasting" @selected(old('fasting_status') === 'non_fasting')>{{ __('Non-fasting') }}</option>
                </select>
                @error('fasting_status')<p class="error">{{ $message }}</p>@enderror
                <label for="fasting_duration_hours">{{ __('Fasting duration (hours, when fasting)') }}</label>
                <input id="fasting_duration_hours" name="fasting_duration_hours" type="number" min="0" step="any" value="{{ old('fasting_duration_hours') }}">
                @error('fasting_duration_hours')<p class="error">{{ $message }}</p>@enderror
                <label for="last_meal_at">{{ __('Last meal time (when non-fasting)') }}</label>
                <input id="last_meal_at" name="last_meal_at" type="time" value="{{ old('last_meal_at') }}">
                @error('last_meal_at')<p class="error">{{ $message }}</p>@enderror

                <h2>{{ __('Peak Flow Meter') }}</h2>
                <p class="muted">{{ __('Enter each valid attempt in L/min. The highest valid attempt is recorded; attempts are not averaged.') }}</p>
                <div class="grid">
                    @foreach (['i' => __('Attempt I'), 'ii' => __('Attempt II'), 'iii' => __('Attempt III')] as $key => $label)
                        <div>
                            <label for="pef_attempt_{{ $key }}">{{ $label }} (L/min)</label>
                            <input id="pef_attempt_{{ $key }}" name="pef_attempt_{{ $key }}" type="number" min="0.01" step="any" value="{{ old('pef_attempt_'.$key) }}">
                            @error('pef_attempt_'.$key)<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>

                <label for="notes">{{ __('Notes / follow-up') }}</label>
                <textarea id="notes" name="notes" rows="4" maxlength="5000">{{ old('notes') }}</textarea>
                @error('notes')<p class="error">{{ $message }}</p>@enderror
                <p class="muted">{{ __('Examiner') }}: {{ $examiner_name }}</p>
                <p class="muted">{{ __('Screening result; not a diagnosis.') }}</p>
                <div class="actions"><button type="submit">{{ __('Save MCU examination') }}</button><a href="{{ route('operator.one-stop-mcu.index') }}">{{ __('Cancel') }}</a></div>
            </form>
        @endif
    </div>
</section>
@endsection
