<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorArrivals;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Domain\Models\OperatorArrival;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

final class OperatorArrivalResource extends Resource
{
    protected static ?string $model = OperatorArrival::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operator';

    protected static ?string $navigationLabel = 'Arrivals';

    protected static ?string $modelLabel = 'Arrival';

    protected static ?string $pluralModelLabel = 'Arrivals';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Arrival record')->schema([
                TextEntry::make('id')->label('Arrival ID'),
                TextEntry::make('booking_id')->label('Booking ID'),
                TextEntry::make('member_summary')->label('Member')->state(fn (OperatorArrival $record): string => self::memberSummary($record)),
                TextEntry::make('member_schedule_id')->label('Member schedule'),
                TextEntry::make('site.display_name')->label('Site'),
                TextEntry::make('profile.display_name')->label('Operator'),
                TextEntry::make('occurrence_at')->label('Occurrence at')->dateTime(),
                TextEntry::make('recorded_at')->label('Recorded at')->dateTime(),
                TextEntry::make('status')->label('Status'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Arrival ID')->searchable(),
            TextColumn::make('booking_id')->label('Booking ID')->searchable(),
            TextColumn::make('member_summary')->label('Member')->state(fn (OperatorArrival $record): string => self::memberSummary($record)),
            TextColumn::make('member_schedule_id')->label('Member schedule')->searchable(),
            TextColumn::make('site.display_name')->label('Site')->searchable(),
            TextColumn::make('profile.display_name')->label('Operator')->searchable(),
            TextColumn::make('occurrence_at')->label('Occurrence at')->dateTime()->sortable(),
            TextColumn::make('recorded_at')->label('Recorded at')->dateTime(),
            TextColumn::make('status')->label('Status'),
        ])->actions([])->headerActions([])->toolbarActions([])->defaultSort('occurrence_at', 'desc')->emptyStateHeading('No arrivals');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['site', 'profile']);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOperatorArrivals::route('/'), 'view' => Pages\ViewOperatorArrival::route('/{record}')];
    }

    public static function canViewAny(): bool
    {
        return self::authorized();
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    private static function authorized(): bool
    {
        try {
            app(OperatorAuthorization::class)->auditRead();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function memberSummary(OperatorArrival $record): string
    {
        try {
            app(OperatorAuthorization::class)->auditRead();
            $member = app(OperatorAttendanceContract::class)->safeArrivalSummary((string) $record->booking_id);

            return $member === null ? 'Member unavailable' : ($member['member_name'].' · '.$member['medical_record_number']);
        } catch (Throwable) {
            return 'Member unavailable';
        }
    }
}
