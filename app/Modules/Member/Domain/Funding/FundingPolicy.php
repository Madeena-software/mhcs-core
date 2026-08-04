<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Funding;

final class FundingPolicy
{
    public static function assertAllowed(string $bookingType, FundingSource|string $source): FundingSource
    {
        try {
            $source = $source instanceof FundingSource ? $source : FundingSource::from($source);
        } catch (\ValueError $exception) {
            throw new FundingPolicyException('Unknown funding sources fail closed.', previous: $exception);
        }

        $bookingType = strtolower(trim($bookingType));

        if (
            ($bookingType === 'b2b' && $source !== FundingSource::BusinessReserved)
            || ($bookingType === 'b2c' && $source !== FundingSource::Personal)
            || ! in_array($bookingType, ['b2b', 'b2c'], true)
        ) {
            throw new FundingPolicyException('Booking funding and source are incompatible.');
        }

        return $source;
    }
}
