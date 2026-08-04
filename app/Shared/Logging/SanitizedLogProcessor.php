<?php

declare(strict_types=1);

namespace App\Shared\Logging;

use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Security\SensitiveDataSanitizer;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final readonly class SanitizedLogProcessor implements ProcessorInterface
{
    public function __construct(private AuthenticatedContextProvider $context) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $current = $this->context->current();
        $context = SanitizedLogContext::sanitize($record->context);
        $extra = SanitizedLogContext::sanitize($record->extra);

        if ($current->operationId !== null) {
            $context['correlation_id'] = (string) $current->operationId;
        }

        return $record->with(
            message: SensitiveDataSanitizer::sanitizeLogMessage($record->message),
            context: $context,
            extra: $extra,
        );
    }
}
