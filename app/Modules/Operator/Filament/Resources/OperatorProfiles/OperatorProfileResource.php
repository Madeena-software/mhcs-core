<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorProfiles;

use App\Models\User;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

final class OperatorProfileResource extends Resource
{
    protected static ?string $model = OperatorProfile::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operator';

    protected static ?string $navigationLabel = 'Operator profiles';

    protected static ?string $modelLabel = 'Operator profile';

    protected static ?string $pluralModelLabel = 'Operator profiles';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Shared User link')->schema([
                Select::make('user_id')
                    ->label('Shared User')
                    ->options(fn (): array => User::query()->where('account_status', 'active')->where('login_enabled', true)->where('must_change_password', false)->whereNotExists(fn ($query) => $query->selectRaw('1')->from('operator_profiles')->whereColumn('operator_profiles.user_id', 'users.id'))->orderBy('email')->pluck('email', 'id')->all())
                    ->required()->searchable(),
                TextInput::make('display_name')->label('Display name')->maxLength(191),
                TextInput::make('employee_code')->label('Employee code')->maxLength(64),
                Toggle::make('active')->label('Active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Operator profile')->schema([
                TextEntry::make('user.email')->label('Shared User'),
                TextEntry::make('display_name')->label('Display name'),
                TextEntry::make('employee_code')->label('Employee code'),
                TextEntry::make('active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.email')->label('Shared User')->searchable(),
            TextColumn::make('display_name')->label('Display name')->searchable(),
            TextColumn::make('employee_code')->label('Employee code')->searchable(),
            TextColumn::make('active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
        ])->actions(self::canManage() ? [EditAction::make()->url(fn (OperatorProfile $record): string => self::getUrl('edit', ['record' => $record]))] : [])->headerActions([])->toolbarActions([])->emptyStateHeading('No Operator profiles');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperatorProfiles::route('/'),
            'create' => Pages\CreateOperatorProfile::route('/create'),
            'view' => Pages\ViewOperatorProfile::route('/{record}'),
            'edit' => Pages\EditOperatorProfile::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::authorized('profileRead');
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
        return self::canManage();
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
        return self::authorized('profileManage');
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
