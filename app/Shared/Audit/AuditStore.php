<?php

declare(strict_types=1);

namespace App\Shared\Audit;

interface AuditStore
{
    public function append(AuditEvent $event): void;
}
