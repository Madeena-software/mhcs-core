<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorSiteReferenceSynchronizer;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class Mvp04OperatorSiteReferenceService implements OperatorSiteReferenceSynchronizer
{
    public function __construct(
        private AuditStore $audit,
        private AuthenticatedContextProvider $context,
        private Clock $clock,
    ) {}

    /** @return array{organization_id: string, site_id: string} */
    public function synchronize(
        string $organizationId,
        string $organizationName,
        string $siteId,
        string $code,
        string $name,
        string $timezone,
        bool $active,
        string $sourceVersion,
    ): array {
        foreach ([$organizationId, $organizationName, $siteId, $code, $name, $timezone, $sourceVersion] as $value) {
            if (trim($value) === '') {
                throw new Mvp03Exception('Operator site synchronization requires complete stable fields.');
            }
        }

        return DB::transaction(function () use ($organizationId, $organizationName, $siteId, $code, $name, $timezone, $active, $sourceVersion): array {
            $now = $this->clock->now();
            $organization = DB::table('operator_organization_refs')
                ->where('operator_organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if ($organization === null) {
                $organizationLocalId = (string) Str::uuid();
                DB::table('operator_organization_refs')->insert([
                    'id' => $organizationLocalId,
                    'operator_organization_id' => $organizationId,
                    'name' => $organizationName,
                    'source_version' => $sourceVersion,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $organization = (object) ['id' => $organizationLocalId, 'name' => $organizationName, 'source_version' => $sourceVersion, 'active' => true];
            } else {
                $this->assertVersion((string) $organization->source_version, $sourceVersion);
                if ((string) $organization->source_version === $sourceVersion && ($organization->name !== $organizationName || ! $organization->active)) {
                    throw new Mvp03Exception('The Operator organization replay conflicts with its stored version.');
                }
                if ($organization->name !== $organizationName || ! $organization->active || $organization->source_version !== $sourceVersion) {
                    DB::table('operator_organization_refs')->where('id', $organization->id)->update([
                        'name' => $organizationName,
                        'source_version' => $sourceVersion,
                        'active' => true,
                        'updated_at' => $now,
                    ]);
                    $organization = (object) ['id' => $organization->id, 'name' => $organizationName, 'source_version' => $sourceVersion, 'active' => true];
                }
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
                    'active' => $active,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $site = (object) ['id' => $localSiteId, 'operator_organization_ref_id' => $organization->id, 'code' => $code, 'display_name' => $name, 'timezone' => $timezone, 'source_version' => $sourceVersion, 'active' => $active];
            } else {
                if ((string) $site->operator_organization_ref_id !== (string) $organization->id) {
                    throw new Mvp03Exception('An Operator site cannot change its organization reference.');
                }
                $this->assertVersion((string) $site->source_version, $sourceVersion);
                if ((string) $site->source_version === $sourceVersion && ($site->code !== $code || $site->display_name !== $name || $site->timezone !== $timezone || (bool) $site->active !== $active)) {
                    throw new Mvp03Exception('The Operator site replay conflicts with its stored version.');
                }
                if ($site->code !== $code || $site->display_name !== $name || $site->timezone !== $timezone || (bool) $site->active !== $active || $site->source_version !== $sourceVersion) {
                    DB::table('examination_site_refs')->where('id', $site->id)->update([
                        'code' => $code,
                        'display_name' => $name,
                        'timezone' => $timezone,
                        'source_version' => $sourceVersion,
                        'active' => $active,
                        'updated_at' => $now,
                    ]);
                }
            }

            $context = $this->context->current();
            if ($context->purpose === null) {
                $context = $context->forPurpose('operator.site.sync');
            }
            $this->audit->append(AuditEvent::fromContext(
                $context,
                'member.site-reference.synchronized',
                'member',
                'success',
                $now,
                'examination-site-reference',
                (string) $site->id,
                metadata: ['operator_site_id' => $siteId, 'source_version' => $sourceVersion, 'active' => $active],
            ));

            return ['organization_id' => (string) $organization->id, 'site_id' => (string) $site->id];
        });
    }

    private function assertVersion(string $existing, string $incoming): void
    {
        if ($existing === '' || $existing === $incoming) {
            return;
        }
        if (ctype_digit($existing) && ctype_digit($incoming) && (int) $incoming < (int) $existing) {
            throw new Mvp03Exception('A stale Operator site reference version was rejected.');
        }
    }
}
