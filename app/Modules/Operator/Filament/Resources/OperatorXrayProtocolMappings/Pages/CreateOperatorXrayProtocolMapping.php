<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\Pages;

use App\Modules\Operator\Application\Services\OperatorXrayProtocolConfigurationService;
use App\Modules\Operator\Domain\Models\OperatorXrayProtocolMapping;
use App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\OperatorXrayProtocolMappingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateOperatorXrayProtocolMapping extends CreateRecord
{
    protected static string $resource = OperatorXrayProtocolMappingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $result = app(OperatorXrayProtocolConfigurationService::class)->publish(
            $data['service_offering_id'] ?? null,
            0,
            self::projectionIdentifiers($data['projection_identifiers'] ?? null),
            $data['operation_id'] ?? null,
        );

        return OperatorXrayProtocolMapping::query()->findOrFail($result['mapping_id']);
    }

    /** @return list<string> */
    private static function projectionIdentifiers(mixed $value): array
    {
        return is_string($value) ? preg_split('/\R/u', trim($value)) ?: [] : [];
    }
}
