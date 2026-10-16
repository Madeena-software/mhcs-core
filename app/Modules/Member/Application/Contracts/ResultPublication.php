<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface ResultPublication
{
    public function manage(string $case, string $action, array $input = []): array;

    public function read(string $publication, int $generation, bool $entry = false): array;

    public function denied(): void;
}
