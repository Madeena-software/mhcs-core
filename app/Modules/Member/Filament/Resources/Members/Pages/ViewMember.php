<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\Members\Pages;

use App\Models\User;
use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Domain\Models\Member;
use App\Modules\Member\Filament\Resources\Members\MemberAuditRecord;
use App\Modules\Member\Filament\Resources\Members\MemberResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class ViewMember extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MemberResource::class;

    public function mount(int|string $record): void
    {
        $this->mountInteractsWithTable();
        parent::mount($record);
    }

    public function content(Schema $schema): Schema
    {
        $components = [$this->getInfolistContentComponent()];

        if ($this->canReadAudit()) {
            $components[] = Section::make('Audit Member')
                ->schema([EmbeddedTable::make()]);
        }

        return $schema->components($components);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Audit Member')
            ->columns([
                TextColumn::make('occurred_at')->label('Waktu')->dateTime()->sortable(),
                TextColumn::make('action')->label('Aksi'),
                TextColumn::make('outcome')->label('Hasil'),
                TextColumn::make('actor_id')->label('Actor ID'),
                TextColumn::make('target_label')->label('Target'),
                TextColumn::make('target_id')->label('Target ID'),
                TextColumn::make('reason')->label('Alasan'),
                TextColumn::make('correlation_id')->label('Correlation ID'),
            ])
            ->actions([])
            ->headerActions([])
            ->toolbarActions([])
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10)
            ->defaultSort('occurred_at', 'desc')
            ->emptyStateHeading('Belum ada audit Member');
    }

    protected function getTableQuery(): Builder
    {
        /** @var Member $member */
        $member = $this->getRecord();

        return MemberAuditRecord::query()
            ->select([
                'event_id',
                'occurred_at',
                'action',
                'outcome',
                'actor_id',
                'target_id',
                'reason',
                'correlation_id',
            ])
            ->selectRaw("(CASE WHEN target_type = ? THEN 'Member' WHEN target_type = ? THEN 'User' ELSE 'Lainnya' END) AS target_label", [Member::class, User::class])
            ->where('source', 'member')
            ->where(function (Builder $query) use ($member): void {
                $query
                    ->where(function (Builder $query) use ($member): void {
                        $query->where('target_type', Member::class)->where('target_id', $member->getKey());
                    })
                    ->orWhere(function (Builder $query) use ($member): void {
                        $query->where('target_type', User::class)->where('target_id', $member->user_id);
                    });
            });
    }

    private function canReadAudit(): bool
    {
        try {
            app(MemberAuthorization::class)->auditRead();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
