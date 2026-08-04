<?php

declare(strict_types=1);

namespace App\Shared\Adapters;

interface AuthenticatedExternalAdapter
{
    public function identity(): string;

    public function audience(): string;

    public function credential(): ?string;
}
