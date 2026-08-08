<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings;

use App\Modules\Member\Application\Contracts\OperatorServiceOfferingQuery;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Domain\Models\OperatorXrayProtocolMapping;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

final class OperatorXrayProtocolMappingResource extends Resource
{
    protected static ?string $model = OperatorXrayProtocolMapping::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operator';

    protected static ?string $navigationLabel = 'X-ray protocols';

    protected static ?string $modelLabel = 'X-ray protocol';

    protected static ?string $pluralModelLabel = 'X-ray protocols';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Service protocol')->schema([
                Select::make('service_offering_id')
                    ->label('Member service')
                    ->options(self::serviceOptions(...))
                    ->required()
                    ->searchable()
                    ->disabledOn('edit'),
                Textarea::make('projection_identifiers')
                    ->label('Projection identifiers')
                    ->helperText('One synthetic identifier per line. Order is preserved.')
                    ->rows(6)
                    ->required(),
                Hidden::make('operation_id')->default(static fn (): string => (string) Str::uuid()),
                Hidden::make('expected_version')->default(0),
            ])->columns(1),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Current protocol')->schema([
                TextEntry::make('service_code_snapshot')->label('Service code'),
                TextEntry::make('current_version')->label('Current version'),
                TextEntry::make('projection_identifiers')->label('Projection identifiers')->formatStateUsing(self::projectionText(...)),
                TextEntry::make('published_by_user_id')->label('Published by'),
                TextEntry::make('published_at')->label('Published at')->dateTime(),
            ])->columns(2),
            Section::make('Immutable version history')->schema([
                RepeatableEntry::make('versions')->schema([
                    TextEntry::make('version')->label('Version'),
                    TextEntry::make('service_code_snapshot')->label('Service code'),
                    TextEntry::make('projection_identifiers')->label('Projection identifiers')->formatStateUsing(self::projectionText(...)),
                    TextEntry::make('published_by_user_id')->label('Published by'),
                    TextEntry::make('published_at')->label('Published at')->dateTime(),
                ])->columns(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $actions = [ViewAction::make()];
        if (self::canManage()) {
            $actions[] = EditAction::make()
                ->label('Publish next version')
                ->url(fn (OperatorXrayProtocolMapping $record): string => self::getUrl('publish', ['record' => $record]));
        }

        return $table->columns([
            TextColumn::make('service_code_snapshot')->label('Service code')->searchable()->sortable(),
            TextColumn::make('current_version')->label('Current version')->sortable(),
            TextColumn::make('projection_identifiers')->label('Projection identifiers')->formatStateUsing(self::projectionText(...)),
            TextColumn::make('published_at')->label('Published at')->dateTime()->sortable(),
        ])->actions($actions)->headerActions([])->toolbarActions([])->defaultSort('published_at', 'desc')->emptyStateHeading('No X-ray protocols');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('versions');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperatorXrayProtocolMappings::route('/'),
            'create' => Pages\CreateOperatorXrayProtocolMapping::route('/create'),
            'view' => Pages\ViewOperatorXrayProtocolMapping::route('/{record}'),
            'publish' => Pages\PublishNextOperatorXrayProtocolMapping::route('/{record}/publish'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::authorized('protocolRead');
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

    /** @return array<string, string> */
    private static function serviceOptions(): array
    {
        $options = [];
        foreach (app(OperatorServiceOfferingQuery::class)->active() as $offering) {
            $options[$offering['id']] = $offering['code'];
        }

        return $options;
    }

    private static function projectionText(mixed $state): string
    {
        return is_array($state) ? implode(', ', $state) : '';
    }

    private static function canManage(): bool
    {
        return self::authorized('protocolManage');
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
