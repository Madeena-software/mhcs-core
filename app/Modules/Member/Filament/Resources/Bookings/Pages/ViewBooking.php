<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\Bookings\Pages;

use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\Models\LocalImagingOrder;
use App\Modules\Member\Domain\Models\PointLedgerEntry;
use App\Modules\Member\Filament\Resources\Bookings\BookingAuditRecord;
use App\Modules\Member\Filament\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ViewBooking extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = BookingResource::class;

    public function mount(int|string $record): void
    {
        $this->mountInteractsWithTable();
        parent::mount($record);
    }

    public function content(Schema $schema): Schema
    {
        $components = [$this->getInfolistContentComponent()];
        if (BookingResource::canReadAudit()) {
            $components[] = Section::make('Audit booking')->schema([EmbeddedTable::make()]);
        }

        return $schema->components($components);
    }

    public function table(Table $table): Table
    {
        return $table->heading('Audit booking')->columns([
            TextColumn::make('occurred_at')->label('Waktu')->dateTime(),
            TextColumn::make('action')->label('Aksi'),
            TextColumn::make('outcome')->label('Hasil'),
            TextColumn::make('reason')->label('Alasan'),
        ])->actions([])->headerActions([])->toolbarActions([])->paginated([10, 25]);
    }

    protected function getTableQuery(): Builder
    {
        if (! BookingResource::canReadAudit()) {
            return BookingAuditRecord::query()->whereNull('event_id');
        }

        /** @var Booking $booking */
        $booking = $this->getRecord();

        return BookingAuditRecord::query()
            ->select(['event_id', 'occurred_at', 'action', 'outcome', 'reason'])
            ->where('source', 'member')
            ->where('outcome', 'success')
            ->whereNull('reason')
            ->where(function (Builder $query) use ($booking): void {
                $query->where(function (Builder $query) use ($booking): void {
                    $query->where('action', 'member.booking.confirmed')
                        ->where('target_type', Booking::class)
                        ->where('target_id', $booking->getKey());
                })->orWhere(function (Builder $query) use ($booking): void {
                    $query->where('action', 'member.point-charge')
                        ->where('target_type', PointLedgerEntry::class)
                        ->whereIn('target_id', PointLedgerEntry::query()
                            ->select('id')
                            ->where('booking_id', $booking->getKey()));
                })->orWhere(function (Builder $query) use ($booking): void {
                    $query->where('action', 'member.imaging-order.create')
                        ->where('target_type', LocalImagingOrder::class)
                        ->whereIn('target_id', LocalImagingOrder::query()
                            ->select('id')
                            ->where('booking_id', $booking->getKey()));
                });
            })
            ->orderByDesc('occurred_at');
    }
}
