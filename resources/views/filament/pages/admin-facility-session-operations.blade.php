<x-filament-panels::page>
 <div class="flex flex-wrap gap-4">
  <div><label for="board-facility">Lokasi</label><select id="board-facility" wire:model.live="facility"><option value="">Pilih lokasi</option>@foreach($facilities['values'] ?? [] as $site)<option value="{{ $site['reference'] }}">{{ $site['label'] }}{{ $site['active'] ? '' : ' (Tidak aktif)' }}</option>@endforeach</select></div>
  <div><label for="board-date">Tanggal Operasional</label><input id="board-date" type="date" wire:model.live="date"></div>
  <x-filament::button id="board-refresh" wire:click="refreshBoard">Segarkan</x-filament::button>
 </div>
 <nav aria-label="Pengelolaan" class="flex flex-wrap gap-4">
 @if($siteManage)<a href="{{ \App\Modules\Operator\Filament\Resources\OperatorSites\OperatorSiteResource::getUrl('index') }}">Kelola Fasilitas</a>@endif
 @if($scheduleManage)<a href="{{ \App\Modules\Member\Filament\Resources\ShiftSchedules\ShiftScheduleResource::getUrl('create') }}">Buat Jadwal</a>@endif
 @if($memberRead)<a href="{{ \App\Modules\Member\Filament\Resources\Members\MemberResource::getUrl('index') }}">Direktori Member</a>@endif
 @if($registration)<a href="{{ \App\Modules\Member\Filament\Resources\Members\MemberResource::getUrl('create') }}">Registrasi Member</a>@endif
 </nav>
 @if($board['state'] !== 'available')
 <p role="status">{{ $board['state']==='denied' ? 'Tidak diizinkan' : 'Data tidak tersedia' }}</p>
 @elseif(!$board['values'])<p role="status">Pilih lokasi untuk melihat jadwal.</p>
 @else
 <p>{{ $board['values']['facility']['label'] }} · {{ $board['values']['facility']['timezone'] }}{{ $board['values']['facility']['active'] ? '' : ' · Tidak aktif (riwayat)' }}</p>
 <p>Rentang UTC: {{ implode(' — ', $board['values']['range']) }}</p>
 <p>Diamati: {{ (new \DateTimeImmutable($board['observed_at']))->setTimezone(new \DateTimeZone($board['values']['facility']['timezone']))->format('Y-m-d H:i:s P') }}. Ringkasan waktu baca; setiap bagian dibaca terpisah.</p>
 @if(!$board['values']['sessions'])<p role="status">Belum ada jadwal pada tanggal ini.</p>@endif
 @foreach($board['values']['sessions'] as $id=>$session)
 <section class="rounded border p-4" aria-label="{{ $session['reference'] }}">
 <h2>{{ $session['reference'] }} · {{ $session['service_label'] }}</h2>
 <p>{{ $session['start'] }} — {{ $session['end'] }} · {{ $session['status']==='open'?'Terbuka':'Ditutup' }} · Kuota: {{ $session['quota'] }}</p>
 @if($scheduleManage)<a href="{{ \App\Modules\Member\Filament\Resources\ShiftSchedules\ShiftScheduleResource::getUrl('edit',['record'=>$id]) }}">Edit Jadwal {{ $session['reference'] }}</a>@endif
 <h3>Booking dan Kapasitas</h3>
 @if($bookings['state']==='available')
 <p>Total Booking: {{ $bookings['values'][$id]['total'] }} · Kapasitas Tercadangkan: {{ $bookings['values'][$id]['reserved'] }} · Sisa Kapasitas: {{ max(0, $bookings['values'][$id]['remaining']) }}</p>
 @if($bookings['values'][$id]['reserved'] > $session['quota'])<p role="alert">Melebihi kuota</p>@endif
 <dl>@foreach($bookings['values'][$id]['by_status'] as $status=>$count)<dt>{{ ['pending_payment' => 'Menunggu pembayaran', 'confirmed' => 'Terkonfirmasi', 'arrived' => 'Tiba', 'checked_in' => 'Check-in', 'in_progress' => 'Sedang berlangsung', 'postponed' => 'Ditunda', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'no_show' => 'Tidak hadir'][$status] }}</dt><dd>{{ $count }}</dd>@endforeach</dl>
 <p>Diamati: {{ (new \DateTimeImmutable($bookings['observed_at']))->setTimezone(new \DateTimeZone($board['values']['facility']['timezone']))->format('Y-m-d H:i:s P') }}</p>
 @else<p role="status">{{ $bookings['state']==='denied'?'Tidak diizinkan':'Data tidak tersedia' }}</p>@endif
 <h3>Kedatangan Tercatat</h3>
 @if($arrivals['state']==='available')<p>Total Kedatangan Tercatat: {{ $arrivals['values'][$id] }}</p><p>Diamati: {{ (new \DateTimeImmutable($arrivals['observed_at']))->setTimezone(new \DateTimeZone($board['values']['facility']['timezone']))->format('Y-m-d H:i:s P') }}</p>@else<p role="status">{{ $arrivals['state']==='denied'?'Tidak diizinkan':'Data tidak tersedia' }}</p>@endif
 </section>
 @endforeach
 <div class="flex gap-4">@if($page>1)<x-filament::button wire:click="previousPage">Sebelumnya</x-filament::button>@endif @if($board['values']['more'])<x-filament::button wire:click="nextPage">Berikutnya</x-filament::button>@endif</div>
 @endif
</x-filament-panels::page>
