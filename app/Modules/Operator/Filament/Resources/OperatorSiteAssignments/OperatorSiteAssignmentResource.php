<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorSiteAssignments;

use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Application\Services\OperatorSiteAssignmentService;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Modules\Operator\Domain\Models\OperatorSite;
use App\Modules\Operator\Domain\Models\OperatorSiteAssignment;
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

final class OperatorSiteAssignmentResource extends Resource
{
    protected static ?string $model = OperatorSiteAssignment::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operator';

    protected static ?string $navigationLabel = 'Site assignments';

    protected static ?string $modelLabel = 'Site assignment';

    protected static ?string $pluralModelLabel = 'Site assignments';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Assignment')->schema([
                Select::make('operator_profile_id')->label('Operator profile')->options(fn (): array => OperatorProfile::query()->where('active', true)->with('user')->get()->mapWithKeys(fn (OperatorProfile $profile): array => [$profile->id => $profile->display_name ?: $profile->user?->email])->all())->required()->searchable(),
                Select::make('operator_site_id')->label('Physical site')->options(fn (): array => OperatorSite::query()->where('active', true)->orderBy('display_name')->pluck('display_name', 'id')->all())->required()->searchable(),
            ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Assignment')->schema([
                TextEntry::make('profile.display_name')->label('Operator'),
                TextEntry::make('profile.user.email')->label('Shared User'),
                TextEntry::make('site.display_name')->label('Site'),
                TextEntry::make('active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Revoked'),
                TextEntry::make('assigned_at')->label('Assigned at')->dateTime(),
                TextEntry::make('revoked_at')->label('Revoked at')->dateTime(),
                TextEntry::make('reason')->label('Reason'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('profile.display_name')->label('Operator')->searchable(),
            TextColumn::make('profile.user.email')->label('Shared User')->searchable(),
            TextColumn::make('site.display_name')->label('Site')->searchable(),
            TextColumn::make('active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Revoked'),
            TextColumn::make('assigned_at')->label('Assigned at')->dateTime()->sortable(),
            TextColumn::make('revoked_at')->label('Revoked at')->dateTime(),
        ])->actions([
            Action::make('revoke')
                ->label('Revoke')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(1000)])
                ->visible(fn (OperatorSiteAssignment $record): bool => $record->active && self::canManage())
                ->action(function (OperatorSiteAssignment $record, array $data, Component $livewire): void {
                    try {
                        app(OperatorSiteAssignmentService::class)->revoke($record, (string) $data['reason']);
                        $livewire->resetTable();
                    } catch (Throwable) {
                    }
                }),
        ])->headerActions([])->toolbarActions([])->defaultSort('assigned_at', 'desc')->emptyStateHeading('No site assignments');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['profile.user', 'site']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperatorSiteAssignments::route('/'),
            'create' => Pages\CreateOperatorSiteAssignment::route('/create'),
            'view' => Pages\ViewOperatorSiteAssignment::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::authorized('assignmentRead');
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
        return self::authorized('assignmentManage');
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
