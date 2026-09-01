<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\ImageGateway\Application\Services\ProductionDicomRemediationService;
use Illuminate\Console\Command;
use Throwable;

final class RemediateProductionDicom extends Command
{
    protected $signature = 'mhcs:remediate-production-dicom {mode} {--preflight} {--execute}';

    protected $description = 'Run one bounded exact-case production DICOM remediation.';

    public function handle(ProductionDicomRemediationService $remediation): int
    {
        $stage = $this->option('preflight') ? 'preflight' : ($this->option('execute') ? 'execute' : null);
        if ($stage === null || ($this->option('preflight') && $this->option('execute'))) {
            $this->error('Exactly one of --preflight or --execute is required.');

            return self::INVALID;
        }
        try {
            $result = $remediation->run($this->argument('mode'), $stage, getenv('REMEDIATION_MPIPS_REVISION') ?: null, getenv('REMEDIATION_MPIPS_FIX') ?: null);
            foreach ($result as $key => $value) {
                $this->line($key.'='.(is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR)));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('production_dicom_remediation=FAIL');
            $this->line('failure_category=PRECONDITION_OR_INTEGRITY');

            return self::FAILURE;
        }
    }
}
