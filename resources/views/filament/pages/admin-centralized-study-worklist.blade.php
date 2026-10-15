<x-filament-panels::page>
 <a href="{{ \App\Providers\Filament\Pages\AdminFacilitySessionOperations::getUrl() }}">{{ __('Back to facility operations') }}</a>
 <div class="flex flex-wrap gap-4">
  <div><label for="study-facility">{{ __('Facility') }}</label><select id="study-facility" wire:model.live="facility"><option value="">{{ __('Select facility') }}</option>@foreach($facilities['values'] ?? [] as $site)<option value="{{ $site['reference'] }}">{{ $site['label'] }}{{ $site['active'] ? '' : ' · '.__('Inactive historical facility') }}</option>@endforeach</select></div>
  <div><label for="study-date">{{ __('Operational date') }}</label><input id="study-date" type="date" wire:model.live="date"></div>
  <div><label for="study-session">{{ __('Session') }}</label><select id="study-session" wire:model.live="session"><option value="">{{ __('Select session') }}</option>@foreach($directory['values']['sessions'] ?? [] as $id=>$entry)<option value="{{ $id }}">{{ $entry['reference'] }} · {{ $entry['start'] }}</option>@endforeach</select></div>
  <x-filament::button id="study-refresh" wire:click="refreshWorklist">{{ __('Refresh worklist') }}</x-filament::button>
 </div>
 <nav aria-label="{{ __('Session pages') }}" class="flex gap-4">@if($sessionPage>1)<x-filament::button id="study-sessions-previous" wire:click="previousSessions">{{ __('Previous sessions') }}</x-filament::button>@endif @if($directory['values']['more'] ?? false)<x-filament::button id="study-sessions-next" wire:click="nextSessions">{{ __('Next sessions') }}</x-filament::button>@endif</nav>
 <p>{{ __('AI readiness is not radiologist approval, a diagnosis, or publication to patients.') }}</p>
 @if($directory['state'] !== 'available')
  <p role="status">{{ $directory['state']==='denied' ? __('Study access denied') : __('Study data unavailable') }}</p>
 @elseif(!$directory['values'])
  <p role="status">{{ __('Select facility and date to see sessions.') }}</p>
 @elseif(!$directory['values']['sessions'])
  <p role="status">{{ __('No sessions on this operational date.') }}</p>
 @endif
 @if($worklist['state'] !== 'available')
  <p role="status">{{ $worklist['state']==='denied' ? __('Study access denied') : __('Study data unavailable') }}</p>
 @elseif(!$worklist['values'])
  <p role="status">{{ __('Select a session to see X-ray intakes.') }}</p>
 @else
  <p>{{ $worklist['values']['facility']['label'] }} · {{ $worklist['values']['facility']['timezone'] }}{{ $worklist['values']['facility']['active'] ? '' : ' · '.__('Inactive historical facility') }}</p>
  <h2>{{ $worklist['values']['session']['reference'] }} · {{ $worklist['values']['session']['service_label'] }}</h2>
  <p>{{ $worklist['values']['session']['start'] }} — {{ $worklist['values']['session']['end'] }}</p>
  <p>{{ __('Observed at') }}: {{ (new \DateTimeImmutable($worklist['observed_at']))->setTimezone(new \DateTimeZone($worklist['values']['facility']['timezone']))->format('Y-m-d H:i:s P') }}</p>
  @if(!$worklist['values']['rows'])<p role="status">{{ __('No X-ray intakes in this session.') }}</p>@endif
  <div class="overflow-x-auto"><table><caption>{{ __('X-ray operational progress') }}</caption><thead><tr>@foreach(['Session ticket', 'Study reference', 'Capture receipt', 'Processing state', 'DICOM record', 'AI state'] as $heading)<th scope="col">{{ __($heading) }}</th>@endforeach</tr></thead><tbody>
  @foreach($worklist['values']['rows'] as $row)
   <tr><th scope="row">{{ $worklist['values']['session']['reference'] }} / {{ $row['ticket_reference'] }}</th><td>{{ $row['study_reference'] ?? __('No study reference') }}@if($row['study_reference'] && app(\App\Modules\Operator\Application\Services\RadiographerWorkspace::class)->managementAvailable()) <a href="{{ route('radiographer.manage', $row['study_reference']) }}">Penugasan Radiografer / QC</a><small>{{ app(\App\Modules\Operator\Application\Services\RadiographerWorkspace::class)->indicator($row['study_reference']) }}</small>@endif</td><td>{{ __('study.receipt.'.$row['receipt']) }}</td><td>{{ __('study.processing.'.$row['processing']) }}</td><td>{{ __('study.dicom.'.$row['dicom']) }}</td><td>{{ __('study.ai.'.$row['ai']) }}</td></tr>
  @endforeach
  </tbody></table></div>
  <nav aria-label="{{ __('Intake pages') }}" class="flex gap-4">@if($page>1)<x-filament::button id="study-previous" wire:click="previousPage">{{ __('Previous intakes') }}</x-filament::button>@endif @if($worklist['values']['more'])<x-filament::button id="study-next" wire:click="nextPage">{{ __('Next intakes') }}</x-filament::button>@endif</nav>
 @endif
</x-filament-panels::page>
