<?php

declare(strict_types=1);

namespace App\Shared\Logging;

use Illuminate\Log\Logger;

final class SanitizedLogTap
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(app(SanitizedLogProcessor::class));
    }
}
