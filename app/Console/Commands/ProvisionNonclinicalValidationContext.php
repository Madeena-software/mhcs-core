<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Services\NonclinicalValidationContextProvisioningService;
use App\Shared\Validation\NonclinicalValidationAccountProvisioningService;
use Illuminate\Console\Command;
use Throwable;

final class ProvisionNonclinicalValidationContext extends Command
{
    protected $signature = 'mhcs:provision-nonclinical-validation-context';

    protected $description = 'Provision the fixed nonclinical validation context.';

    public function handle(NonclinicalValidationContextProvisioningService $provisioner): int
    {
        $secret = getenv(NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME);
        if (! is_string($secret) || trim($secret) === '') {
            $this->error('validation_context_provisioning=FAIL');
            $this->line('failure_category=SECRET_REQUIRED');

            return self::FAILURE;
        }
        try {
            foreach ($provisioner->provision($secret) as $key => $value) {
                $this->line($key.'='.($value === true ? 'true' : ($value === false ? 'false' : $value)));
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('validation_context_provisioning=FAIL');
            $this->line('failure_category=SAFE_PROVISIONING_FAILURE');

            return self::FAILURE;
        }
    }
}
