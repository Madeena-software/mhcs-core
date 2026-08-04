<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain\Security;

use RuntimeException;

final class UntrustedImagePolicyException extends RuntimeException {}
