<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ShiftSchedules;

use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Application\Services\Mvp03BookingService;
use App\Modules\Member\Domain\Models\ExaminationSiteReference;
use App\Modules\Member\Domain\Models\ServiceOffering;
use App\Modules\Member\Domain\Models\ShiftSchedule;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

final class ShiftScheduleResource extends Resource
{
    protected static ?string $model = ShiftSchedule::class;

    protected static string|UnitEnum|null $navigationGroup = 'Member';

    protected static ?string $navigationLabel = 'Jadwal Member';

    protected static ?string $modelLabel = 'Jadwal';

    protected static ?string $pluralModelLabel = 'Jadwal Member';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jadwal Sesi Foto Radiografi')->schema([
                Select::make('examination_site_id')->label('Lokasi')->options(fn (): array => ExaminationSiteReference::query()->where('active', true)->orderBy('display_name')->pluck('display_name', 'id')->all())->required()->searchable(),
                Select::make('service_offering_id')->label('Layanan')->options(fn (): array => ServiceOffering::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())->required()->searchable(),
                TextInput::make('starts_at')->label('Mulai (ISO 8601 dengan offset)')->required()->formatStateUsing(static fn (mixed $state): mixed => $state instanceof \DateTimeInterface ? $state->format('Y-m-d\\TH:i:sP') : $state)->placeholder('2030-01-10T10:00:00+07:00'),
                TextInput::make('ends_at')->label('Selesai (ISO 8601 dengan offset)')->required()->formatStateUsing(static fn (mixed $state): mixed => $state instanceof \DateTimeInterface ? $state->format('Y-m-d\\TH:i:sP') : $state)->placeholder('2030-01-10T11:00:00+07:00'),
                TextInput::make('quota')->label('Kuota')->required()->numeric()->minValue(5)->maxValue(20),
                Select::make('status')->label('Status')->options(['open' => 'Terbuka', 'closed' => 'Ditutup'])->default('open')->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('display_reference')->label(__('Schedule reference'))->searchable(),
            TextColumn::make('site.display_name')->label('Lokasi')->searchable(),
            TextColumn::make('service.name')->label('Layanan')->searchable(),
            TextColumn::make('starts_at')->label('Mulai')->dateTime()->sortable(),
            TextColumn::make('quota')->label('Kuota'),
            TextColumn::make('status')->label('Status')->formatStateUsing(fn (string $state): string => $state === 'open' ? 'Terbuka' : 'Ditutup'),
            TextColumn::make('confirmed_count')->label('Terkonfirmasi')->state(fn (ShiftSchedule $record): int => $record->bookings()->whereIn('status', Mvp03BookingService::capacityStatuses())->count()),
        ])->actions(self::authorized('scheduleManage') ? [
            EditAction::make()
                ->url(fn (ShiftSchedule $record): string => self::getUrl('edit', ['record' => $record])),
        ] : [])->headerActions([])->toolbarActions([])->defaultSort('starts_at')->emptyStateHeading('Belum ada jadwal');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShiftSchedules::route('/'),
            'create' => Pages\CreateShiftSchedule::route('/create'),
            'view' => Pages\ViewShiftSchedule::route('/{record}'),
            'edit' => Pages\EditShiftSchedule::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::authorized('scheduleRead');
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::authorized('scheduleManage');
    }

    public static function canEdit(Model $record): bool
    {
        return self::authorized('scheduleManage');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
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
