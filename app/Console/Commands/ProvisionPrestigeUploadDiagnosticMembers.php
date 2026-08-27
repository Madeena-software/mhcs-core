<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Services\PrestigeUploadDiagnosticProvisioningService;
use Illuminate\Console\Command;
use Throwable;

final class ProvisionPrestigeUploadDiagnosticMembers extends Command
{
    protected $signature = 'mhcs:provision-prestige-upload-diagnostic-members';

    protected $description = 'Provision the fixed Prestige nonclinical upload-diagnostic Members.';

    public function handle(PrestigeUploadDiagnosticProvisioningService $provisioner): int
    {
        try {
            foreach ($provisioner->provision() as $key => $value) {
                $this->line($key.'='.($value === true ? 'true' : ($value === false ? 'false' : $value)));
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('provisioning=FAIL');

            return self::FAILURE;
        }
    }
}
