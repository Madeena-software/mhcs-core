<p role="status" id="assignment-status">{{ ['unassigned' => 'Belum ditugaskan', 'active' => 'Akses aktif', 'expired' => 'Akses kedaluwarsa', 'revoked' => 'Akses dicabut', 'unavailable' => 'Akses tidak tersedia'][$state['access_status']] }}</p>
@if(!empty($state['assignee']))<p>Individu yang ditugaskan: <span id="current-assignee">{{ $state['assignee'] }}</span></p>@endif
@if(!empty($state['expires_at']))<p>Berlaku hingga: <span id="assignment-expiry">{{ $state['expires_at'] }} UTC</span></p>@endif
