<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorEligibleShifts;

use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Domain\Models\OperatorEligibleShift;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

final class OperatorEligibleShiftResource extends Resource
{
    protected static ?string $model = OperatorEligibleShift::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operator';

    protected static ?string $navigationLabel = 'Eligible shifts';

    protected static ?string $modelLabel = 'Eligible shift';

    protected static ?string $pluralModelLabel = 'Eligible shifts';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Eligible schedule reference')->schema([
                TextEntry::make('member_schedule_id')->label('Member schedule'),
                TextEntry::make('operator_site_id')->label('Operator site'),
                TextEntry::make('schedule_starts_at')->label('Starts at')->dateTime(),
                TextEntry::make('schedule_ends_at')->label('Ends at')->dateTime(),
                TextEntry::make('confirmed_count_at_eligibility')->label('Confirmed count'),
                TextEntry::make('quota')->label('Quota'),
                TextEntry::make('event_version')->label('Event version'),
                TextEntry::make('source_event_id')->label('Source event'),
                TextEntry::make('sync_status')->label('Sync status'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('member_schedule_id')->label('Member schedule')->searchable(),
            TextColumn::make('operator_site_id')->label('Operator site')->searchable(),
            TextColumn::make('schedule_starts_at')->label('Starts at')->dateTime()->sortable(),
            TextColumn::make('confirmed_count_at_eligibility')->label('Confirmed'),
            TextColumn::make('quota')->label('Quota'),
            TextColumn::make('sync_status')->label('Status'),
        ])->actions([])->headerActions([])->toolbarActions([])->defaultSort('schedule_starts_at')->emptyStateHeading('No eligible shifts');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOperatorEligibleShifts::route('/'), 'view' => Pages\ViewOperatorEligibleShift::route('/{record}')];
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
            app(OperatorAuthorization::class)->shiftRead();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
