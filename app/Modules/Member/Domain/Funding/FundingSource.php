<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Funding;

enum FundingSource: string
{
    case Personal = 'personal';
    case BusinessReserved = 'business_reserved';
}
