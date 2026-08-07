<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Illuminate\Support\Str;
use JsonSerializable;

final class SensitiveDataSanitizer
{
    private const SENSITIVE_KEY = '/(?:password|passphrase|secret|token|authorization|cookie|credential|private.?key|nik|kk|patient|member|clinical|npz|dicom|bank|account.?number|routing)/i';

    public static function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if ($value instanceof JsonSerializable) {
            return self::sanitize($value->jsonSerialize(), $key);
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $itemKey => $item) {
                $stringKey = is_string($itemKey) ? $itemKey : null;
                $sanitized[$itemKey] = self::sanitize($item, $stringKey);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            return '[OBJECT]';
        }

        if (is_resource($value)) {
            return '[RESOURCE]';
        }

        if (self::isSensitiveScalar($value)) {
            return '[REDACTED]';
        }

        return $value;
    }

    public static function sanitizeLogMessage(string $message): string
    {
        if (self::isSensitiveScalar($message)) {
            return '[REDACTED]';
        }

        $message = preg_replace(
            '/((?:password|passphrase|secret|token|authorization|cookie|credential)\s*[:=]\s*)\S+/i',
            '$1[REDACTED]',
            $message,
        ) ?? '[REDACTED]';

        $message = preg_replace('/\bBearer\s+\S+/i', 'Bearer [REDACTED]', $message) ?? '[REDACTED]';

        return preg_replace('/\b\d{10,20}\b/', '[REDACTED]', $message) ?? '[REDACTED]';
    }

    public static function assertSafeString(?string $value): void
    {
        if ($value !== null && self::isSensitiveScalar($value)) {
            throw new SensitivePayloadException('Sensitive data is not allowed in a security record.');
        }
    }

    public static function assertSafe(array $value): void
    {
        self::assertSafeValue($value);
    }

    public static function isSensitiveKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_KEY, $key) === 1;
    }

    private static function assertSafeValue(mixed $value, ?string $key = null): void
    {
        if ($key !== null && self::isSensitiveKey($key)) {
            throw new SensitivePayloadException("Sensitive audit metadata key [{$key}] is not allowed.");
        }

        if ($value instanceof JsonSerializable) {
            self::assertSafeValue($value->jsonSerialize(), $key);

            return;
        }

        if (is_array($value)) {
            foreach ($value as $itemKey => $item) {
                self::assertSafeValue($item, is_string($itemKey) ? $itemKey : null);
            }

            return;
        }

        if (self::isSensitiveScalar($value)) {
            throw new SensitivePayloadException('Sensitive scalar values are not allowed in audit metadata.');
        }

        if (is_object($value) || is_resource($value)) {
            throw new SensitivePayloadException('Objects and resources are not allowed in audit metadata.');
        }
    }

    private static function isSensitiveScalar(mixed $value): bool
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if (Str::isUuid($value)) {
            return false;
        }

        return preg_match('/\b\d{10,20}\b/', $value) === 1
            || preg_match('/\bBearer\s+\S+/i', $value) === 1
            || preg_match('/(?:password|passphrase|secret|token|authorization|cookie|credential|api[_ -]?key|client[_ -]?secret)/i', $value) === 1
            || preg_match('/(?:patient|clinical|diagnos(?:is|ed)|symptom|medical|radiograph|x[- ]?ray|treatment|medication|disease|history|chest pain|shortness of breath|cough|fever|tuberculosis)/i', $value) === 1
            || preg_match('/(?:npz|dicom|DICM|payload|application\/(?:x-npz|dicom))/i', $value) === 1
            || str_contains($value, "PK\x03\x04")
            || preg_match('/\Adata:[^;]+;base64,/i', $value) === 1
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1;
    }
}
