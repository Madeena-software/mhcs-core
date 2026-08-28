@extends('operator.layout')

@section('title', __('Detail Jadwal'))

@section('content')
<section aria-labelledby="schedule-detail-title">
    <p class="eyebrow">{{ __('Front desk') }}</p>
    <h1 id="schedule-detail-title"><code>{{ $schedule['display_reference'] }}</code></h1>
    <section class="card">
        <div class="grid">
            <div><strong>{{ __('Site') }}</strong><br>{{ $site['display_name'] }} ({{ $site['code'] }})</div>
            <div><strong>{{ __('Layanan') }}</strong><br>{{ $schedule['service_name'] }} ({{ $schedule['service_code'] }})</div>
            <div><strong>{{ __('Waktu') }}</strong><br>{{ $schedule['starts_at'] }} – {{ $schedule['ends_at'] }}</div>
            <div><strong>{{ __('Peserta') }}</strong><br>{{ $schedule['participant_count'] }} / {{ $schedule['quota'] }}</div>
        </div>
    </section>

    <section class="card" aria-labelledby="add-participant-title">
        <h2 id="add-participant-title">{{ __('Tambah Peserta') }}</h2>
        <p class="muted">{{ __('Cari dengan nama, MRN, email, atau telepon. NIK tidak ditampilkan dalam pencarian.') }}</p>
        <form method="GET" action="{{ route('operator.schedules.show', $schedule['id']) }}">
            <label for="member-search">{{ __('Cari Member') }}</label>
            <input id="member-search" name="q" value="{{ $search }}" minlength="2" maxlength="100" required>
            <button type="submit">{{ __('Cari') }}</button>
        </form>
        @if ($search !== '')
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ __('Nama') }}</th><th>{{ __('MRN') }}</th><th>{{ __('Kontak') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($member_results as $member)
                        <tr>
                            <td>{{ $member['member_name'] }}</td>
                            <td><code>{{ $member['medical_record_number'] }}</code></td>
                            <td>{{ $member['email'] ?? '—' }}<br>{{ $member['phone'] ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('operator.schedules.participants.store', $schedule['id']) }}">
                                    @csrf
                                    <input type="hidden" name="member_id" value="{{ $member['member_id'] }}">
                                    <input type="hidden" name="operation_id" value="{{ Illuminate\Support\Str::uuid() }}">
                                    <button type="submit">{{ __('Tambah') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('Member tidak ditemukan.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
        <p><a href="{{ route('operator.members.create', ['schedule_id' => $schedule['id']]) }}">{{ __('Register Member Baru dan tambahkan') }}</a></p>
    </section>

    <section class="card" aria-labelledby="participant-roster-title">
        <h2 id="participant-roster-title">{{ __('Daftar Peserta') }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('Nama') }}</th><th>{{ __('MRN') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @forelse ($participants as $participant)
                    <tr><td>{{ $participant['member_name'] }}</td><td><code>{{ $participant['medical_record_number'] }}</code></td><td class="status">{{ __($participant['booking_status']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">{{ __('Belum ada peserta.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
