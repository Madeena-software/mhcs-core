<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Domain\Models\GrabberClient;
use Illuminate\Support\Str;

final readonly class GrabberClientService
{
    /**
     * @return array{client: GrabberClient, raw_token: string}
     */
    public function create(
        string $grabberId,
        string $name,
        string $operatorSiteId,
        ?string $rawToken = null,
        string $status = 'active',
    ): array {
        $grabberId = trim($grabberId);
        $name = trim($name);
        $operatorSiteId = trim($operatorSiteId);
        $rawToken = $rawToken !== null && trim($rawToken) !== '' ? trim($rawToken) : Str::random(40);
        $tokenHash = hash('sha256', $rawToken);

        $client = GrabberClient::query()->create([
            'id' => (string) Str::uuid(),
            'grabber_id' => $grabberId,
            'name' => $name,
            'operator_site_id' => $operatorSiteId,
            'token_hash' => $tokenHash,
            'status' => $status,
        ]);

        return [
            'client' => $client,
            'raw_token' => $rawToken,
        ];
    }

    public function authenticate(?string $rawToken, ?string $grabberId = null): ?GrabberClient
    {
        if ($rawToken === null || trim($rawToken) === '') {
            return null;
        }

        $tokenHash = hash('sha256', trim($rawToken));

        $query = GrabberClient::query()->where('token_hash', $tokenHash);

        if ($grabberId !== null && trim($grabberId) !== '') {
            $query->where('grabber_id', trim($grabberId));
        }

        return $query->first();
    }
}
