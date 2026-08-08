<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\Pages;

use App\Modules\Operator\Application\Services\OperatorXrayProtocolConfigurationService;
use App\Modules\Operator\Domain\Models\OperatorXrayProtocolMapping;
use App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\OperatorXrayProtocolMappingResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class PublishNextOperatorXrayProtocolMapping extends EditRecord
{
    protected static string $resource = OperatorXrayProtocolMappingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['projection_identifiers'] = implode(PHP_EOL, (array) ($data['projection_identifiers'] ?? []));
        $data['expected_version'] = (int) ($data['current_version'] ?? 0);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $result = app(OperatorXrayProtocolConfigurationService::class)->publish(
            $record->service_offering_id,
            $data['expected_version'] ?? null,
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
