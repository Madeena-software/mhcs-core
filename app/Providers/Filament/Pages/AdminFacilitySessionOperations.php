<?php

declare(strict_types=1);

namespace App\Providers\Filament\Pages;

use App\Modules\Member\Application\Services\AdminBookingSummaryRead;
use App\Modules\Member\Application\Services\AdminSessionBoardRead;
use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Domain\Enums\RegistrationSource;
use App\Modules\Operator\Application\Services\AdminSessionArrivalRead;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Shared\Authorization\AdminOperationalRead;
use App\Shared\Time\Clock;
use Filament\Pages\Dashboard;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

final class AdminFacilitySessionOperations extends Dashboard
{
    protected static ?string $title = 'Operasional Fasilitas';

    protected string $view = 'filament.pages.admin-facility-session-operations';

    #[Url]
    public string $facility = '';

    #[Url]
    public string $date = '';

    #[Url]
    public int $page = 1;

    #[Locked]
    public bool $dateExplicit = false;

    private string $requestKind = 'initial';

    public function mount(): void
    {
        $this->dateExplicit = $this->date !== '';
        if ($this->date === '') {
            $this->date = app(Clock::class)->now()->format('Y-m-d');
        }
    }

    public function hydrate(): void
    {
        $this->requestKind = 'filter';
    }

    public function updatedFacility(): void
    {
        $this->page = 1;
        $this->requestKind = 'filter';
        if (! $this->dateExplicit) {
            $result = app(AdminSessionBoardRead::class)->read($this->facility, $this->date, 1, 'filter');
            if ($result['state'] === 'available') {
                $this->date = app(Clock::class)->now()->setTimezone(new \DateTimeZone($result['values']['facility']['timezone']))->format('Y-m-d');
            }
        }
    }

    public function updatedDate(): void
    {
        $this->dateExplicit = true;
        $this->page = 1;
        $this->requestKind = 'filter';
    }

    public function refreshBoard(): void
    {
        $this->requestKind = 'refresh';
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    protected function getViewData(): array
    {
        $read = app(AdminSessionBoardRead::class);
        $facilities = $read->facilities($this->requestKind);
        $board = ['state' => $facilities['state'], 'values' => null, 'observed_at' => null];
        $bookings = $arrivals = ['state' => 'denied', 'values' => null, 'observed_at' => null];
        if ($facilities['state'] === 'available' && $this->facility !== '') {
            $board = $read->read($this->facility, $this->date, $this->page, $this->requestKind);
            if (! $this->dateExplicit && $board['state'] === 'available') {
                $localDate = app(Clock::class)->now()->setTimezone(new \DateTimeZone($board['values']['facility']['timezone']))->format('Y-m-d');
                if ($localDate !== $this->date) {
                    $this->date = $localDate;
                    $board = $read->read($this->facility, $this->date, $this->page, $this->requestKind);
                }
            }
            if ($board['state'] === 'available') {
                $v = $board['values'];
                $quotas = array_map(fn ($s) => $s['quota'], $v['sessions']);
                $bookings = app(AdminBookingSummaryRead::class)->read($quotas, $v['facility']['reference'], $v['metadata']);
                $arrivals = app(AdminSessionArrivalRead::class)->read($v['facility']['reference'], $v['operator_site'], array_keys($v['sessions']), $v['metadata']);
                unset($board['values']['operator_site'], $board['values']['metadata']);
            }
        }
        $gate = app(AdminOperationalRead::class);
        if (! $gate->allowed(fn () => app(MemberAuthorization::class)->scheduleRead())) {
            $facilities = $board = ['state' => 'denied', 'values' => null, 'observed_at' => null];
            $bookings = $arrivals = $board;
        }
        if (! $gate->allowed(fn () => app(MemberAuthorization::class)->bookingRead())) {
            $bookings = ['state' => 'denied', 'values' => null, 'observed_at' => null];
        }
        if (! $gate->allowed(fn () => app(OperatorAuthorization::class)->auditRead())) {
            $arrivals = ['state' => 'denied', 'values' => null, 'observed_at' => null];
        }

        return compact('facilities', 'board', 'bookings', 'arrivals') + ['scheduleManage' => $gate->allowed(fn () => app(MemberAuthorization::class)->scheduleManage()), 'siteManage' => $gate->allowed(fn () => app(OperatorAuthorization::class)->siteManage()) && $gate->allowed(fn () => app(OperatorAuthorization::class)->siteRead()), 'memberRead' => $gate->allowed(fn () => app(MemberAuthorization::class)->accountRead()), 'registration' => $gate->allowed(fn () => app(MemberAuthorization::class)->registration(RegistrationSource::Administrator, true))];
    }
}
