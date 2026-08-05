<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\Bookings;

use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Domain\Models\Booking;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

final class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;
    protected static string|UnitEnum|null $navigationGroup = 'Member';
    protected static ?string $navigationLabel = 'Booking Member';
    protected static ?string $modelLabel = 'Booking';
    protected static ?string $pluralModelLabel = 'Booking Member';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan booking')->schema([
                TextEntry::make('id')->label('ID booking'),
                TextEntry::make('member.name')->label('Nama Member'),
                TextEntry::make('member.medical_record_number')->label('Nomor rekam medis'),
                TextEntry::make('service_code_snapshot')->label('Kode layanan'),
                TextEntry::make('site_name_snapshot')->label('Lokasi'),
                TextEntry::make('point_cost_snapshot')->label('Harga Madeena Points'),
                TextEntry::make('booking_type')->label('Jenis booking'),
                TextEntry::make('funding_source')->label('Sumber dana'),
                TextEntry::make('status')->label('Status'),
                TextEntry::make('includes_ai_snapshot')->label('Bantuan interpretasi otomatis')->formatStateUsing(fn (bool $state): string => $state ? 'Termasuk' : 'Tidak termasuk'),
                TextEntry::make('includes_doctor_snapshot')->label('Peninjauan dokter')->formatStateUsing(fn (bool $state): string => $state ? 'Termasuk' : 'Tidak termasuk'),
                TextEntry::make('confirmed_at')->label('Dikonfirmasi')->dateTime(),
                TextEntry::make('imagingOrder.id')->label('Referensi order lokal'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('ID booking')->searchable(),
            TextColumn::make('member.name')->label('Member')->searchable(),
            TextColumn::make('member.medical_record_number')->label('Nomor rekam medis')->searchable(),
            TextColumn::make('service_code_snapshot')->label('Layanan'),
            TextColumn::make('site_name_snapshot')->label('Lokasi'),
            TextColumn::make('booking_type')->label('Jenis'),
            TextColumn::make('funding_source')->label('Dana'),
            TextColumn::make('point_cost_snapshot')->label('Harga'),
            TextColumn::make('status')->label('Status'),
            TextColumn::make('confirmed_at')->label('Dikonfirmasi')->dateTime()->sortable(),
        ])->actions([])->headerActions([])->toolbarActions([])->defaultSort('confirmed_at', 'desc')->emptyStateHeading('Belum ada booking');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['member', 'imagingOrder']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool { return self::authorized('bookingRead'); }
    public static function canView(Model $record): bool { return self::canViewAny(); }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function canReadAudit(): bool
    {
        return self::authorized('bookingAuditRead');
    }

    private static function authorized(string $method): bool
    {
        try {
            app(MemberAuthorization::class)->{$method}();
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
