<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

use Illuminate\Http\Client\Response;

interface DicomConverter
{
    public function convert(string $radiograph, string $gain, string $manifest): Response;
}
