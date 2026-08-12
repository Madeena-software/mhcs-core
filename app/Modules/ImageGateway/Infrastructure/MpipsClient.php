<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MpipsClient
{
    public function convert(string $radiograph, string $gain, string $manifest): Response
    {
        $baseUrl = config('mhcs.mpips.base_url');
        $apiKey = config('mhcs.mpips.api_key');

        if (! is_string($baseUrl) || trim($baseUrl) === '' || ! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('MPIPS client configuration is incomplete.');
        }

        return Http::timeout((int) config('mhcs.mpips.timeout_seconds', 360))
            ->withHeaders(['X-MPIPS-API-Key' => $apiKey])
            ->attach('radiograph_npz', $radiograph, 'radiograph.npz')
            ->attach('gain_npz', $gain, 'gain.npz')
            ->attach('manifest', $manifest, 'manifest.json')
            ->post(rtrim($baseUrl, '/').'/v1/radiographs/dicom');
    }
}
