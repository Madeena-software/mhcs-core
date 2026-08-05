<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorSiteReferenceSynchronizer;
use App\Modules\Operator\Domain\Models\OperatorSite;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OperatorSiteService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorSiteReferenceSynchronizer $memberReferences,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OperatorSite
    {
        $context = $this->authorization->siteManage();
        $data = $this->data($attributes);

        return DB::transaction(function () use ($context, $data): OperatorSite {
            if (OperatorSite::query()->where('operator_site_id', $data['operator_site_id'])->exists()) {
                throw new OperatorException('site_conflict', 'The Operator site identifier is already used.');
            }
            $id = (string) Str::uuid();
            $now = $this->clock->now();
            OperatorSite::query()->create(['id' => $id, ...$data]);
            $site = OperatorSite::query()->findOrFail($id);
            $this->sync($site);
            $this->record($context, $site, 'create');
            $this->event($context, $site, $now);

            return $site;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(OperatorSite $site, array $attributes): OperatorSite
    {
        $context = $this->authorization->siteManage();

        return DB::transaction(function () use ($site, $attributes, $context): OperatorSite {
            $record = OperatorSite::query()->whereKey($site->getKey())->lockForUpdate()->first();
            if ($record === null) {
                throw new OperatorException('site_unavailable', 'The Operator site is unavailable.');
            }
            $data = $this->data($attributes, $record);
            if ($data['operator_site_id'] !== $record->operator_site_id || $data['organization_id'] !== $record->organization_id) {
                throw new OperatorException('site_immutable', 'Stable Operator site identity cannot change.');
            }
            if ($data['source_version'] === $record->source_version && $this->siteChanged($record, $data)) {
                $data['source_version'] = $this->nextVersion((string) $record->source_version);
            }
            $record->forceFill($data)->save();
            $this->sync($record->refresh());
            $this->record($context, $record, 'update');
            $this->event($context, $record, $this->clock->now());

            return $record->refresh();
        });
    }

    public function setActive(OperatorSite $site, bool $active): OperatorSite
    {
        return $this->update($site, ['active' => $active, 'source_version' => $this->nextVersion((string) $site->source_version)]);
    }

    /** @return array<string, mixed> */
    private function data(array $attributes, ?OperatorSite $existing = null): array
    {
        $read = static function (string $key, ?string $fallback = null) use ($attributes, $existing): mixed {
            return array_key_exists($key, $attributes) ? $attributes[$key] : ($existing?->{$key} ?? $fallback);
        };
        $values = [];
        foreach (['operator_site_id', 'organization_id', 'organization_name', 'code', 'display_name', 'timezone', 'source_version'] as $key) {
            $value = $read($key);
            if (! is_string($value) || trim($value) === '') {
                throw new OperatorException('site_invalid', 'Operator site fields are incomplete.');
            }
            $values[$key] = trim($value);
        }
        $active = $read('active', '1');
        if (! is_bool($active) && ! in_array($active, [0, 1, '0', '1'], true)) {
            throw new OperatorException('site_invalid', 'Operator site status is invalid.');
        }
        $values['active'] = (bool) $active;
        $values['address_line'] = $read('address_line');
        if ($values['address_line'] !== null && ! is_string($values['address_line'])) {
            throw new OperatorException('site_invalid', 'Operator site address is invalid.');
        }

        return $values;
    }

    private function sync(OperatorSite $site): void
    {
        $this->memberReferences->synchronize(
            $site->organization_id,
            $site->organization_name,
            $site->operator_site_id,
            $site->code,
            $site->display_name,
            $site->timezone,
            $site->active,
            $site->source_version,
        );
    }

    /** @param array<string, mixed> $data */
    private function siteChanged(OperatorSite $site, array $data): bool
    {
        foreach (['organization_name', 'code', 'display_name', 'address_line', 'timezone', 'active'] as $key) {
            if ($site->{$key} !== $data[$key]) {
                return true;
            }
        }

        return false;
    }

    private function nextVersion(string $version): string
    {
        return ctype_digit($version)
            ? (string) ((int) $version + 1)
            : $version.'-'.substr(hash('sha256', $this->clock->now()->format(DATE_ATOM)), 0, 8);
    }

    private function record(AuthenticatedContext $context, OperatorSite $site, string $action): void
    {
        $this->audit->append(AuditEvent::fromContext(
            $context,
            'operator.site.'.$action,
            'operator',
            'success',
            $this->clock->now(),
            OperatorSite::class,
            (string) $site->getKey(),
            metadata: ['operator_site_id' => $site->operator_site_id, 'active' => $site->active, 'source_version' => $site->source_version],
        ));
    }

    private function event(AuthenticatedContext $context, OperatorSite $site, \DateTimeImmutable $now): void
    {
        $this->outbox->record(new VersionedDomainEvent(
            LocalId::fromString((string) Str::uuid()),
            'operator.site.changed',
            1,
            $now,
            ['operator_site_id' => $site->operator_site_id, 'organization_id' => $site->organization_id, 'active' => $site->active, 'source_version' => $site->source_version],
            LocalId::fromString((string) $site->getKey()),
            $context->operationId,
        ));
    }
}
