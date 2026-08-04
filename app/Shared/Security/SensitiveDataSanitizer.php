<?php

declare(strict_types=1);

namespace App\Shared\Security;

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

        if (is_string($value) && preg_match('/\A\d{10,20}\z/', $value) === 1) {
            return '[REDACTED]';
        }

        return $value;
    }

    public static function sanitizeLogMessage(string $message): string
    {
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
        if ($value === null) {
            return;
        }

        if (
            preg_match('/\b\d{10,20}\b/', $value) === 1
            || preg_match('/(?:password|passphrase|secret|token|authorization|cookie|credential)\s*[:=]/i', $value) === 1
        ) {
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

        if (is_object($value) || is_resource($value)) {
            throw new SensitivePayloadException('Objects and resources are not allowed in audit metadata.');
        }
    }
}
