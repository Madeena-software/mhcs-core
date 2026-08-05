<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ServiceOfferings;

use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Domain\Models\ServiceOffering;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

final class ServiceOfferingResource extends Resource
{
    protected static ?string $model = ServiceOffering::class;

    protected static string|UnitEnum|null $navigationGroup = 'Member';

    protected static ?string $navigationLabel = 'Layanan Member';

    protected static ?string $modelLabel = 'Layanan';

    protected static ?string $pluralModelLabel = 'Layanan Member';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Layanan')->schema([
                TextInput::make('code')->label('Kode layanan')->required()->maxLength(64)->regex('/^[A-Z0-9][A-Z0-9_-]{1,63}$/'),
                TextInput::make('name')->label('Nama layanan')->required()->maxLength(255),
                TextInput::make('point_price')->label('Harga Madeena Points')->required()->regex('/^[0-9]+(?:\.[0-9]{1,4})?$/')->maxLength(24),
                Toggle::make('includes_ai')->label('Bantuan interpretasi otomatis'),
                Toggle::make('includes_doctor')->label('Peninjauan dokter'),
                Toggle::make('active')->label('Aktif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable(),
            TextColumn::make('point_price')->label('Harga Points'),
            TextColumn::make('active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Tidak aktif'),
        ])->actions(self::authorized('catalogueManage') ? [
            EditAction::make()
                ->url(fn (ServiceOffering $record): string => self::getUrl('edit', ['record' => $record])),
        ] : [])->headerActions([])->toolbarActions([])->defaultSort('name')->emptyStateHeading('Belum ada layanan');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceOfferings::route('/'),
            'create' => Pages\CreateServiceOffering::route('/create'),
            'view' => Pages\ViewServiceOffering::route('/{record}'),
            'edit' => Pages\EditServiceOffering::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::authorized('catalogueRead');
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::authorized('catalogueManage');
    }

    public static function canEdit(Model $record): bool
    {
        return self::authorized('catalogueManage');
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
