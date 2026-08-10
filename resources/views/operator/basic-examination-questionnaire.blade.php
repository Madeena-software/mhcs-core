@extends('operator.layout')

@section('title', 'Paper questionnaire')

@section('content')
<section aria-labelledby="paper-questionnaire-title">
    <h1 id="paper-questionnaire-title">Paper questionnaire</h1>
    <p class="muted">Complete the approved paper interview, then photograph the completed form. The photo is stored privately and is not shown to Members or the public queue.</p>

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
        <form method="POST" enctype="multipart/form-data" action="{{ route('operator.basic-examination-worklist.questionnaire.store', $admission_id) }}">
            @csrf
            <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div class="field">
                <label><input type="checkbox" name="questionnaire_completed" value="1" @checked(old('questionnaire_completed'))> The approved paper questionnaire has been completed.</label>
            </div>
            <div class="field">
                <label for="photo">Completed paper questionnaire photo (JPEG or PNG)</label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" required>
            </div>
            <button type="submit">Store private questionnaire photo</button>
        </form>
    </section>
</section>
@endsection
