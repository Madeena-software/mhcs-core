<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Idempotency;

use RuntimeException;

final class IdempotencyConflict extends RuntimeException {}
