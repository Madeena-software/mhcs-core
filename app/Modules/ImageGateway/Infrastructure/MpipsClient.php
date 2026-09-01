<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure;

use App\Modules\ImageGateway\Application\Contracts\DicomConverter;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MpipsClient implements DicomConverter
{
    public function convert(string $radiograph, string $gain, string $manifest): Response
    {
        return $this->request($radiograph, $gain, $manifest, false);
    }

    /** @param resource $radiograph @param resource $gain */
    public function convertStreams($radiograph, $gain, string $manifest): PromiseInterface
    {
        return $this->request($radiograph, $gain, $manifest, true);
    }

    private function request(mixed $radiograph, mixed $gain, string $manifest, bool $async): Response|PromiseInterface
    {
        $baseUrl = config('mhcs.mpips.base_url');
        $apiKey = config('mhcs.mpips.api_key');

        if (! is_string($baseUrl) || trim($baseUrl) === '' || ! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('MPIPS client configuration is incomplete.');
        }

        $request = Http::timeout((int) config('mhcs.mpips.timeout_seconds', 360))
            ->withHeaders(['X-MPIPS-API-Key' => $apiKey])
            ->attach('radiograph_npz', $radiograph, 'radiograph.npz')
            ->attach('gain_npz', $gain, 'gain.npz')
            ->attach('manifest', $manifest, 'manifest.json');

        return $request->async($async)->post(rtrim($baseUrl, '/').'/v1/radiographs/dicom');
    }
}
