<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\Mvp03Exception;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class Mvp03SiteReferenceService
{
    public function __construct(
        private AuditStore $audit,
        private AuthenticatedContextProvider $context,
        private Clock $clock,
    ) {}

    /** @return array{organization_id: string, site_id: string} */
    public function bootstrap(string $organizationId, string $organizationName, string $siteId, string $code, string $name, string $timezone, string $sourceVersion = 'synthetic-v1'): array
    {
        if (! app()->environment(['local', 'testing']) && ! (bool) env('MHCS_ALLOW_PRODUCTION_MVP_SEED', false)) {
            throw new Mvp03Exception('Synthetic site references are limited to local and testing environments.');
        }

        return DB::transaction(function () use ($organizationId, $organizationName, $siteId, $code, $name, $timezone, $sourceVersion): array {
            $now = $this->clock->now();
            $organization = DB::table('operator_organization_refs')->where('operator_organization_id', $organizationId)->lockForUpdate()->first();
            if ($organization === null) {
                $organizationIdLocal = (string) Str::uuid();
                DB::table('operator_organization_refs')->insert([
                    'id' => $organizationIdLocal,
                    'operator_organization_id' => $organizationId,
                    'name' => $organizationName,
                    'source_version' => $sourceVersion,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $organization = (object) ['id' => $organizationIdLocal, 'name' => $organizationName];
                $this->record('member.site-reference.bootstrap', 'organization', $organizationIdLocal, ['source_version' => $sourceVersion]);
            } elseif ($organization->name !== $organizationName || ! $organization->active) {
                throw new Mvp03Exception('The existing synthetic organization reference is inconsistent.');
            }

            $site = DB::table('examination_site_refs')->where('operator_site_id', $siteId)->lockForUpdate()->first();
            if ($site === null) {
                $localSiteId = (string) Str::uuid();
                DB::table('examination_site_refs')->insert([
                    'id' => $localSiteId,
                    'operator_site_id' => $siteId,
                    'operator_organization_ref_id' => $organization->id,
                    'code' => $code,
                    'display_name' => $name,
                    'timezone' => $timezone,
                    'source_version' => $sourceVersion,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $site = (object) ['id' => $localSiteId];
                $this->record('member.site-reference.bootstrap', 'site', $localSiteId, ['source_version' => $sourceVersion, 'code' => $code]);
            } elseif (
                $site->operator_organization_ref_id !== $organization->id
                || $site->code !== $code
                || $site->display_name !== $name
                || $site->timezone !== $timezone
                || ! $site->active
            ) {
                throw new Mvp03Exception('The existing synthetic examination-site reference is inconsistent.');
            }

            return ['organization_id' => (string) $organization->id, 'site_id' => (string) $site->id];
        });
    }

    private function record(string $action, string $targetType, string $targetId, array $metadata): void
    {
        $context = $this->context->current();
        if ($context->purpose === null) {
            $context = $context->forPurpose('member.site-reference.bootstrap');
        }

        $this->audit->append(AuditEvent::fromContext(
            $context,
            action: $action,
            source: 'member',
            outcome: 'success',
            occurredAt: $this->clock->now(),
            targetType: $targetType,
            targetId: $targetId,
            metadata: $metadata,
        ));
    }
}
