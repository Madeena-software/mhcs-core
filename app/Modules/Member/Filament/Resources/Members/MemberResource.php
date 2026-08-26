<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\Members;

use App\Models\User;
use App\Modules\Member\Application\Services\AccountStateService;
use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Domain\Models\Member;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;
use UnitEnum;

final class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|UnitEnum|null $navigationGroup = 'Member';

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $modelLabel = 'Member';

    protected static ?string $pluralModelLabel = 'Members';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                'members.id',
                'members.user_id',
                'members.name',
                'members.medical_record_number',
                'members.identity_status',
                'members.identity_document_type',
                'members.birth_date',
                'members.administrative_gender',
                'members.registration_source',
                'members.phone',
                'members.created_at',
                'members.updated_at',
            ])
            ->selectRaw("(CASE WHEN current_address IS NOT NULL AND TRIM(current_address) <> '' THEN 25 ELSE 0 END + CASE WHEN emergency_contact_name IS NOT NULL AND TRIM(emergency_contact_name) <> '' THEN 25 ELSE 0 END + CASE WHEN emergency_contact_relationship IS NOT NULL AND TRIM(emergency_contact_relationship) <> '' THEN 25 ELSE 0 END + CASE WHEN emergency_contact_phone IS NOT NULL AND TRIM(emergency_contact_phone) <> '' THEN 25 ELSE 0 END) AS profile_completion_value")
            ->whereHas('user')
            ->with('user:id,email,account_status,login_enabled,must_change_password');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Member')
                ->schema([
                    TextEntry::make('name')->label('Nama'),
                    TextEntry::make('medical_record_number')->label('Nomor rekam medis'),
                    TextEntry::make('birth_date')->label('Tanggal lahir')->date(),
                    TextEntry::make('administrative_gender')->label('Jenis kelamin administratif')->formatStateUsing(fn (?string $state): string => self::label($state)),
                    TextEntry::make('identity_status')->label('Status identitas')->badge()->formatStateUsing(fn (?string $state): string => self::label($state)),
                    TextEntry::make('identity_document_type')->label('Jenis dokumen identitas')->formatStateUsing(fn (?string $state): string => self::label($state)),
                    TextEntry::make('registration_source')->label('Sumber pendaftaran')->formatStateUsing(fn (?string $state): string => self::label($state)),
                    TextEntry::make('phone')->label('Telepon')->placeholder('Tidak tersedia'),
                    TextEntry::make('profile_completion')
                        ->label('Kelengkapan profil')
                        ->state(fn (Member $record): string => ((int) $record->profile_completion_value).'%'),
                ])
                ->columns(2),
            Section::make('Akun')
                ->schema([
                    TextEntry::make('user.email')->label('Email'),
                    TextEntry::make('user.account_status')->label('Status akun')->badge()->formatStateUsing(fn (?string $state): string => self::label($state)),
                    TextEntry::make('user.login_enabled')->label('Login enabled')->formatStateUsing(fn (?bool $state): string => $state ? 'Ya' : 'Tidak'),
                    TextEntry::make('user.must_change_password')->label('Wajib ganti kata sandi')->formatStateUsing(fn (?bool $state): string => $state ? 'Ya' : 'Tidak'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('medical_record_number')->label('Nomor rekam medis')->searchable(),
                TextColumn::make('user.email')->label('Email')->searchable(),
                TextColumn::make('identity_status')->label('Status identitas')->formatStateUsing(fn (?string $state): string => self::label($state)),
                TextColumn::make('user.account_status')->label('Status akun')->formatStateUsing(fn (?string $state): string => self::label($state)),
                TextColumn::make('profile_completion')
                    ->label('Profil')
                    ->state(fn (Member $record): string => ((int) $record->profile_completion_value).'%'),
                TextColumn::make('created_at')->label('Terdaftar')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('identity_status')
                    ->label('Status identitas')
                    ->options(self::statusOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $value): Builder => $query->where('identity_status', $value))),
                SelectFilter::make('account_status')
                    ->label('Status akun')
                    ->options(['active' => 'Aktif', 'suspended' => 'Ditangguhkan'])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $value): Builder => $query->whereHas('user', fn (Builder $user): Builder => $user->where('account_status', $value)))),
                SelectFilter::make('login_enabled')
                    ->label('Login')
                    ->options(['1' => 'Enabled', '0' => 'Disabled'])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(isset($data['value']) && $data['value'] !== '', fn (Builder $query): Builder => $query->whereHas('user', fn (Builder $user): Builder => $user->where('login_enabled', (bool) $data['value'])))),
                SelectFilter::make('must_change_password')
                    ->label('Wajib ganti kata sandi')
                    ->options(['1' => 'Ya', '0' => 'Tidak'])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(isset($data['value']) && $data['value'] !== '', fn (Builder $query): Builder => $query->whereHas('user', fn (Builder $user): Builder => $user->where('must_change_password', (bool) $data['value'])))),
                SelectFilter::make('registration_source')
                    ->label('Sumber pendaftaran')
                    ->options([
                        'online' => 'Online',
                        'walk_in' => 'Walk-in',
                        'administrator' => 'Administrator',
                        'nonclinical_validation' => 'Nonclinical validation',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $value): Builder => $query->where('registration_source', $value))),
            ])
            ->actions([
                Action::make('suspend')
                    ->label('Suspend')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([self::reasonField()])
                    ->visible(fn (Member $record): bool => $record->user?->account_status === 'active' && self::canManage($record))
                    ->action(function (Member $record, array $data, Component $livewire): void {
                        try {
                            self::transitionAccount($record, 'suspended', $data);
                            $livewire->resetTable();
                            Notification::make()->title('Akun ditangguhkan')->success()->send();
                        } catch (Throwable) {
                            Notification::make()->title('Perubahan akun tidak dapat dilakukan')->danger()->send();
                        }
                    }),
                Action::make('restore')
                    ->label('Restore')
                    ->color('success')
                    ->requiresConfirmation()
                    ->schema([self::reasonField()])
                    ->visible(fn (Member $record): bool => $record->user?->account_status === 'suspended' && self::canManage($record))
                    ->action(function (Member $record, array $data, Component $livewire): void {
                        try {
                            self::transitionAccount($record, 'active', $data);
                            $livewire->resetTable();
                            Notification::make()->title('Akun dipulihkan')->success()->send();
                        } catch (Throwable) {
                            Notification::make()->title('Perubahan akun tidak dapat dilakukan')->danger()->send();
                        }
                    }),
            ])
            ->headerActions([])
            ->toolbarActions([])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada Member');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'view' => Pages\ViewMember::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::canRead();
    }

    public static function canView(Model $record): bool
    {
        return self::canRead();
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

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }

    private static function canRead(): bool
    {
        try {
            app(MemberAuthorization::class)->accountRead();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function canManage(Member $record): bool
    {
        try {
            return (string) Auth::id() !== (string) $record->user_id
                && app(MemberAuthorization::class)->accountState() !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    private static function transitionAccount(Member $record, string $target, array $data): void
    {
        $reason = $data['reason'] ?? null;
        if (! in_array($target, ['active', 'suspended'], true)) {
            throw new \RuntimeException('The requested account-state transition is not supported.');
        }

        if (! is_string($reason) || ($reason = trim($reason)) === '' || mb_strlen($reason, 'UTF-8') > 1000) {
            throw new \RuntimeException('A valid account-state reason is required.');
        }

        $administratorId = Auth::id();
        $administrator = $administratorId === null ? null : User::query()->whereKey($administratorId)->first();
        if (! $administrator instanceof User || ! $administrator->canAuthenticate()) {
            throw new \RuntimeException('An authenticated administrator is required.');
        }

        $member = Member::query()->with('user')->whereKey($record->getKey())->first();
        if (
            $member === null
            || ! $member->user instanceof User
            || (string) $member->user->getKey() !== (string) $member->user_id
        ) {
            throw new \RuntimeException('The Member-to-User linkage is inconsistent.');
        }

        app(MemberAuthorization::class)->accountState();

        $user = User::query()->whereKey($member->user->getKey())->first();
        if (
            $user === null
            || (string) $administrator->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            || (string) $user->getKey() !== (string) $member->user_id
            || (string) $user->account_status !== ($target === 'suspended' ? 'active' : 'suspended')
        ) {
            throw new \RuntimeException('The account-state transition is no longer allowed.');
        }

        if ($target === 'suspended') {
            app(AccountStateService::class)->suspend((string) $user->getKey(), $reason);

            return;
        }

        app(AccountStateService::class)->restore((string) $user->getKey(), $reason);
    }

    private static function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label('Alasan')
            ->required()
            ->maxLength(1000);
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return [
            'pending_verification' => 'Menunggu verifikasi',
            'verified' => 'Terverifikasi',
            'nonclinical_validation' => 'Nonclinical validation',
            'rejected' => 'Ditolak',
        ];
    }

    private static function label(?string $value): string
    {
        return match ($value) {
            'active' => 'Aktif',
            'suspended' => 'Ditangguhkan',
            'pending_verification' => 'Menunggu verifikasi',
            'verified' => 'Terverifikasi',
            'nonclinical_validation' => 'Nonclinical validation',
            'rejected' => 'Ditolak',
            'administrator' => 'Administrator',
            'online' => 'Online',
            'walk_in' => 'Walk-in',
            'male' => 'Laki-laki',
            'female' => 'Perempuan',
            'unspecified' => 'Tidak ditentukan',
            default => $value ?? 'Tidak tersedia',
        };
    }
}
