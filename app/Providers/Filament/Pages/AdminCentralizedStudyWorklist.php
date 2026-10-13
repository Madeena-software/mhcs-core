<?php

declare(strict_types=1);

namespace App\Providers\Filament\Pages;

use App\Modules\ImageGateway\Application\Contracts\AdminStudyWorklist;
use App\Modules\Member\Application\Services\AdminSessionBoardRead;
use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Operator\Application\Contracts\AdminStudyIntakes;
use App\Shared\Authorization\AdminOperationalRead;
use App\Shared\Time\Clock;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

final class AdminCentralizedStudyWorklist extends Page
{
    protected string $view = 'filament.pages.admin-centralized-study-worklist';

    protected static ?string $slug = 'study-worklist';

    #[Url]
    public string $facility = '';

    #[Url]
    public string $date = '';

    #[Url]
    public string $session = '';

    #[Url]
    public int $sessionPage = 1;

    #[Url]
    public int $page = 1;

    #[Locked]
    public bool $dateExplicit = false;

    private string $requestKind = 'initial';

    public static function getNavigationLabel(): string
    {
        return __('Centralized Study Worklist');
    }

    public function getTitle(): string
    {
        return __('Centralized Study Worklist');
    }

    public static function canAccess(): bool
    {
        return app(AdminOperationalRead::class)->allowed(fn () => app(MemberAuthorization::class)->scheduleRead());
    }

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
        $this->session = '';
        $this->page = $this->sessionPage = 1;
    }

    public function updatedDate(): void
    {
        $this->dateExplicit = true;
        $this->updatedFacility();
    }

    public function updatedSession(): void
    {
        $this->page = 1;
    }

    public function refreshWorklist(): void
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

    public function nextSessions(): void
    {
        $this->sessionPage++;
        $this->session = '';
        $this->page = 1;
    }

    public function previousSessions(): void
    {
        $this->sessionPage = max(1, $this->sessionPage - 1);
        $this->session = '';
        $this->page = 1;
    }

    protected function getViewData(): array
    {
        $read = app(AdminSessionBoardRead::class);
        $facilities = $read->facilities($this->requestKind);
        $directory = ['state' => $facilities['state'], 'values' => null, 'observed_at' => null];
        $worklist = ['state' => 'available', 'values' => null, 'observed_at' => null];
        if ($facilities['state'] === 'available' && $this->facility !== '') {
            if ($this->sessionPage < 1 || $this->sessionPage > intdiv(PHP_INT_MAX, 25)) {
                $directory['state'] = 'unavailable';
            } else {
                $directory = $read->read($this->facility, $this->date, $this->sessionPage, $this->requestKind);
                if (! $this->dateExplicit && $directory['state'] === 'available') {
                    $localDate = app(Clock::class)->now()->setTimezone(new \DateTimeZone($directory['values']['facility']['timezone']))->format('Y-m-d');
                    if ($localDate !== $this->date) {
                        $this->date = $localDate;
                        $this->session = '';
                        $this->page = $this->sessionPage = 1;
                        $directory = $read->read($this->facility, $this->date, 1, $this->requestKind);
                    }
                }
            }
            if ($directory['state'] === 'available' && $this->session !== '') {
                $worklist = app(AdminStudyWorklist::class)->read($this->facility, $this->date, $this->session, $this->page, $this->requestKind);
            } elseif ($directory['state'] !== 'available') {
                $worklist = ['state' => $directory['state'], 'values' => null, 'observed_at' => null];
            }
        }
        if ($directory['values']) {
            unset($directory['values']['operator_site'], $directory['values']['metadata']);
        }
        $gate = app(AdminOperationalRead::class);
        if (! $gate->allowed(fn () => app(MemberAuthorization::class)->scheduleRead())) {
            $facilities = $directory = $worklist = ['state' => 'denied', 'values' => null, 'observed_at' => null];
        }
        if (! $gate->allowed(fn () => app(AdminStudyIntakes::class)->authorize())) {
            $worklist = ['state' => 'denied', 'values' => null, 'observed_at' => null];
        }

        return compact('facilities', 'directory', 'worklist');
    }
}
