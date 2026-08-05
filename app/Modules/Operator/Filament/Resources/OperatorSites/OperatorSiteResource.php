<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorSites;

use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Domain\Models\OperatorSite;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

final class OperatorSiteResource extends Resource
{
    protected static ?string $model = OperatorSite::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operator';

    protected static ?string $navigationLabel = 'Operator sites';

    protected static ?string $modelLabel = 'Operator site';

    protected static ?string $pluralModelLabel = 'Operator sites';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Physical site')->schema([
                TextInput::make('operator_site_id')->label('Stable site identifier')->required()->maxLength(191),
                TextInput::make('organization_id')->label('Organization identifier')->required()->maxLength(191),
                TextInput::make('organization_name')->label('Organization name')->required()->maxLength(255),
                TextInput::make('code')->label('Site code')->required()->maxLength(64),
                TextInput::make('display_name')->label('Display name')->required()->maxLength(255),
                Textarea::make('address_line')->label('Address')->maxLength(2000),
                TextInput::make('timezone')->label('Time zone')->required()->maxLength(64),
                TextInput::make('source_version')->label('Source version')->required()->maxLength(64),
                Toggle::make('active')->label('Operationally active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Physical site')->schema([
                TextEntry::make('operator_site_id')->label('Stable site identifier'),
                TextEntry::make('organization_name')->label('Organization'),
                TextEntry::make('code')->label('Code'),
                TextEntry::make('display_name')->label('Name'),
                TextEntry::make('address_line')->label('Address'),
                TextEntry::make('timezone')->label('Time zone'),
                TextEntry::make('active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                TextEntry::make('source_version')->label('Source version'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Code')->searchable()->sortable(),
            TextColumn::make('display_name')->label('Name')->searchable(),
            TextColumn::make('organization_name')->label('Organization')->searchable(),
            TextColumn::make('timezone')->label('Time zone'),
            TextColumn::make('active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
        ])->actions(self::canManage() ? [EditAction::make()->url(fn (OperatorSite $record): string => self::getUrl('edit', ['record' => $record]))] : [])->headerActions([])->toolbarActions([])->defaultSort('display_name')->emptyStateHeading('No Operator sites');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperatorSites::route('/'),
            'create' => Pages\CreateOperatorSite::route('/create'),
            'view' => Pages\ViewOperatorSite::route('/{record}'),
            'edit' => Pages\EditOperatorSite::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::authorized('siteRead');
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
        return self::authorized('siteManage');
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
