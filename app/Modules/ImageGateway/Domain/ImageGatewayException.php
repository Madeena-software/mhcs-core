<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain;

use RuntimeException;

final class ImageGatewayException extends RuntimeException
{
    public function __construct(
        public readonly string $category,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
