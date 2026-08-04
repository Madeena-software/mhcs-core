@extends('member.layout')

@section('title', 'Profil Member')

@section('content')
<section class="card" aria-labelledby="profile-title">
    <h1 id="profile-title">Lengkapi profil Anda</h1>
    <p>Data ini membantu kami menghubungi Anda bila diperlukan. Email dan nomor telepon bersifat opsional.</p>
    <p><strong>Kelengkapan profil: {{ $completionPercentage }}%</strong></p>

    @if ($errors->any())
        <div class="error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('member.profile.update') }}">
        @csrf
        @method('PATCH')

        <label for="email">Email (opsional)</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" aria-describedby="email-error">
        @error('email') <p id="email-error" class="error">{{ $message }}</p> @enderror

        <label for="phone">Nomor telepon (opsional)</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone', $member->phone) }}" autocomplete="tel" aria-describedby="phone-error">
        @error('phone') <p id="phone-error" class="error">{{ $message }}</p> @enderror

        <label for="current_address">Alamat saat ini</label>
        <textarea id="current_address" name="current_address" aria-describedby="current-address-error">{{ old('current_address', $member->current_address) }}</textarea>
        @error('current_address') <p id="current-address-error" class="error">{{ $message }}</p> @enderror

        <label for="emergency_contact_name">Nama kontak darurat</label>
        <input id="emergency_contact_name" name="emergency_contact_name" type="text" value="{{ old('emergency_contact_name', $member->emergency_contact_name) }}" aria-describedby="emergency-name-error">
        @error('emergency_contact_name') <p id="emergency-name-error" class="error">{{ $message }}</p> @enderror

        <label for="emergency_contact_relationship">Hubungan dengan kontak darurat</label>
        <input id="emergency_contact_relationship" name="emergency_contact_relationship" type="text" value="{{ old('emergency_contact_relationship', $member->emergency_contact_relationship) }}" aria-describedby="emergency-relationship-error">
        @error('emergency_contact_relationship') <p id="emergency-relationship-error" class="error">{{ $message }}</p> @enderror

        <label for="emergency_contact_phone">Nomor telepon kontak darurat</label>
        <input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" value="{{ old('emergency_contact_phone', $member->emergency_contact_phone) }}" aria-describedby="emergency-phone-error">
        @error('emergency_contact_phone') <p id="emergency-phone-error" class="error">{{ $message }}</p> @enderror

        <div class="actions">
            <button type="submit">Simpan profil</button>
        </div>
    </form>
</section>
@endsection
