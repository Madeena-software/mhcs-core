<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ExaminationSites;

use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Domain\Models\ExaminationSiteReference;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

final class ExaminationSiteReferenceResource extends Resource
{
    protected static ?string $model = ExaminationSiteReference::class;

    protected static string|UnitEnum|null $navigationGroup = 'Member';

    protected static ?string $navigationLabel = 'Referensi Lokasi';

    protected static ?string $modelLabel = 'Referensi lokasi';

    protected static ?string $pluralModelLabel = 'Referensi lokasi';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Referensi lokasi Operator')->schema([
                TextEntry::make('code')->label('Kode'),
                TextEntry::make('display_name')->label('Nama'),
                TextEntry::make('timezone')->label('Zona waktu'),
                TextEntry::make('active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Tidak aktif'),
                TextEntry::make('operator_site_id')->label('ID sumber Operator'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Kode')->searchable(),
            TextColumn::make('display_name')->label('Nama')->searchable(),
            TextColumn::make('timezone')->label('Zona waktu'),
            TextColumn::make('active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Tidak aktif'),
        ])->actions([])->headerActions([])->toolbarActions([])->emptyStateHeading('Belum ada referensi lokasi');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExaminationSiteReferences::route('/'),
            'view' => Pages\ViewExaminationSiteReference::route('/{record}'),
        ];
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
            app(MemberAuthorization::class)->scheduleRead();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
