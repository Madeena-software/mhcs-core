@extends('member.layout')

@section('title', __('New Radiography Session'))

@section('content')
<section aria-labelledby="services-title">
    <h1 id="services-title">{{ __('New Radiography Session') }}</h1>
    <p>{{ __('Choose the service that suits your needs. Prices use personal Madeena Points.') }}</p>

    @if ($offerings->isEmpty())
        <div class="card"><p class="muted">{{ __('No Radiography Session services are currently available.') }}</p></div>
    @else
        <div class="summary">
            @foreach ($offerings as $offering)
                <article class="card">
                    <h2>{{ $offering->name }}</h2>
                    <p>{{ $offering->pointPrice() }} Madeena Points</p>
                    <p class="muted">
                        @if ($offering->includes_ai) {{ __('Automatic interpretation assistance available.') }} @endif
                        @if ($offering->includes_doctor) {{ __('Doctor review included.') }} @endif
                    </p>
                    <a href="{{ route('member.services.show', $offering->getKey()) }}">{{ __('View schedule') }}</a>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
