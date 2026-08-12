@extends('operator.layout')

@section('title', __('Basic-examination vital signs'))

@section('content')
<section aria-labelledby="vital-signs-title">
    <h1 id="vital-signs-title">{{ __('Basic-examination vital signs') }}</h1>
    <p class="muted">{{ __('Record the approved screening measurements for the current claimed admission.') }}</p>
    <p class="muted">{{ __('Screening result; not a diagnosis.') }}</p>

    @if ($errors->any())
        <div class="alert" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card">
        <form method="POST" action="{{ route('operator.basic-examination-worklist.vital-signs.store', $admission_id) }}">
            @csrf
            <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            @foreach ([
                ['systolic_bp', __('Systolic blood pressure'), 'systolic_bp_value', 'systolic_bp_missing_reason', $units['blood_pressure']],
                ['diastolic_bp', __('Diastolic blood pressure'), 'diastolic_bp_value', 'diastolic_bp_missing_reason', $units['blood_pressure']],
                ['temperature', __('Temperature'), 'temperature_value', 'temperature_missing_reason', $units['temperature']],
                ['height', __('Height'), 'height_value', 'height_missing_reason', $units['height']],
                ['weight', __('Weight'), 'weight_value', 'weight_missing_reason', $units['weight']],
            ] as [$field, $label, $valueField, $reasonField, $unit])
                <div class="field">
                    <label for="{{ $valueField }}">{{ $label }} ({{ $unit }})</label>
                    <input id="{{ $valueField }}" name="{{ $valueField }}" type="number" step="any" value="{{ old($valueField) }}">
                    <label for="{{ $reasonField }}">{{ __('Missing reason') }}</label>
                    <select id="{{ $reasonField }}" name="{{ $reasonField }}">
                        <option value="">{{ __('Provided as a value') }}</option>
                        @foreach (['unavailable', 'refused', 'not_applicable'] as $reason)
                            <option value="{{ $reason }}" @selected(old($reasonField) === $reason)>{{ __($reason) }}</option>
                        @endforeach
                    </select>
                    @error($valueField)<p class="error">{{ $message }}</p>@enderror
                    @error($reasonField)<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach

            <div class="field">
                <label>{{ __('BMI') }} ({{ $units['bmi'] }})</label>
                <p class="muted">{{ __('Calculated by the server from height and weight when both are values.') }}</p>
                <label for="bmi_missing_reason">{{ __('BMI missing reason when it cannot be calculated') }}</label>
                <select id="bmi_missing_reason" name="bmi_missing_reason">
                    <option value="">{{ __('Calculated from height and weight') }}</option>
                    @foreach (['unavailable', 'refused', 'not_applicable'] as $reason)
                        <option value="{{ $reason }}" @selected(old('bmi_missing_reason') === $reason)>{{ __($reason) }}</option>
                    @endforeach
                </select>
                @error('bmi_missing_reason')<p class="error">{{ $message }}</p>@enderror
            </div>

            <button type="submit">{{ __('Save vital signs') }}</button>
        </form>
    </section>
</section>
@endsection
