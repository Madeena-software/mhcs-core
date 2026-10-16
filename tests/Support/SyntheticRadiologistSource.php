<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Doctor\Application\Contracts\RadiologistAccess;
use App\Modules\ImageGateway\Application\Contracts\RadiologistStudySource;
use App\Modules\Member\Application\Contracts\RadiologistCaseIdentity;
use App\Modules\Operator\Application\Contracts\RadiologistHandoff;

/** Command/authentication tests only. Never evidence of real Gateway verification or rendering. */
final class SyntheticRadiologistSource implements RadiologistStudySource
{
    public function __construct(private array $fixture) {}

    public function handoffForStudy(string $reference): ?string
    {
        return app(RadiologistHandoff::class)->forStudy($this->fixture['study']);
    }

    public function assignment(string $handoff): array
    {
        app(RadiologistHandoff::class)->selection($handoff);

        return ['binding' => $this->fixture['binding'], 'modality' => 'DX'];
    }

    public function inspectClinical(string $workspace, int $generation): array
    {
        return app(RadiologistAccess::class)->run($workspace, 'synthetic-source-inspected', function ($w) {
            return ['binding' => $w->binding, 'identity' => app(RadiologistCaseIdentity::class)->resolveClinical($w->id, $w->generation), 'actual' => ['modality' => 'DX', 'rows' => 2, 'columns' => 2], 'display_reference' => $this->fixture['reference'], 'bytes' => $this->fixture['bytes']];
        }, $generation);
    }
}
