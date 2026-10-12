<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\Members\Pages;

use App\Modules\Member\Filament\Resources\Members\MemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Daftar Member')->visible(fn (): bool => MemberResource::canCreate())];
    }

    protected function authorizeAccess(): void
    {
        abort_unless(MemberResource::canViewAny(), 403);
    }
}
