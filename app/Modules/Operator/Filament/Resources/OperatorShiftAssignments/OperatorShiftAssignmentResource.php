<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorShiftAssignments;

use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Application\Services\OperatorShiftAssignmentService;
use App\Modules\Operator\Domain\Models\OperatorEligibleShift;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Modules\Operator\Domain\Models\OperatorShiftAssignment;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Throwable;
use UnitEnum;

final class OperatorShiftAssignmentResource extends Resource
{
    protected static ?string $model = OperatorShiftAssignment::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operator';

    protected static ?string $navigationLabel = 'Shift assignments';

    protected static ?string $modelLabel = 'Shift assignment';

    protected static ?string $pluralModelLabel = 'Shift assignments';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Manual schedule assignment')->schema([
                Select::make('operator_eligible_shift_id')->label('Eligible schedule')->options(fn (): array => OperatorEligibleShift::query()->where('sync_status', 'eligible')->orderBy('schedule_starts_at')->get()->mapWithKeys(fn (OperatorEligibleShift $shift): array => [$shift->id => $shift->member_schedule_id.' — '.$shift->schedule_starts_at])->all())->required()->searchable(),
                Select::make('operator_profile_id')->label('Operator profile')->options(fn (): array => OperatorProfile::query()->where('active', true)->with('user')->get()->mapWithKeys(fn (OperatorProfile $profile): array => [$profile->id => $profile->display_name ?: $profile->user?->email])->all())->required()->searchable(),
            ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Shift assignment')->schema([
                TextEntry::make('eligibleShift.member_schedule_id')->label('Member schedule'),
                TextEntry::make('eligibleShift.operator_site_id')->label('Operator site'),
                TextEntry::make('profile.display_name')->label('Operator'),
                TextEntry::make('status')->label('Status'),
                TextEntry::make('assigned_at')->label('Assigned at')->dateTime(),
                TextEntry::make('revoked_at')->label('Revoked at')->dateTime(),
                TextEntry::make('reason')->label('Reason'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('eligibleShift.member_schedule_id')->label('Member schedule')->searchable(),
            TextColumn::make('eligibleShift.operator_site_id')->label('Operator site'),
            TextColumn::make('profile.display_name')->label('Operator')->searchable(),
            TextColumn::make('status')->label('Status'),
            TextColumn::make('assigned_at')->label('Assigned at')->dateTime()->sortable(),
        ])->actions([
            Action::make('revoke')
                ->label('Revoke')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(1000)])
                ->visible(fn (OperatorShiftAssignment $record): bool => $record->status === 'active' && self::canManage())
                ->action(function (OperatorShiftAssignment $record, array $data, Component $livewire): void {
                    try {
                        app(OperatorShiftAssignmentService::class)->revoke($record, (string) $data['reason']);
                        $livewire->resetTable();
                    } catch (Throwable) {
                    }
                }),
        ])->headerActions([])->toolbarActions([])->defaultSort('assigned_at', 'desc')->emptyStateHeading('No shift assignments');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['eligibleShift', 'profile']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperatorShiftAssignments::route('/'),
            'create' => Pages\CreateOperatorShiftAssignment::route('/create'),
            'view' => Pages\ViewOperatorShiftAssignment::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::authorized('shiftRead');
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::canManage();
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

    private static function canManage(): bool
    {
        return self::authorized('shiftManage');
    }

    private static function authorized(string $method): bool
    {
        try {
            app(OperatorAuthorization::class)->{$method}();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
