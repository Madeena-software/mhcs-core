<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway;

use App\Modules\ImageGateway\Application\Contracts\AiPacsAdapterContract;
use App\Modules\ImageGateway\Application\Contracts\ImageGatewayAiServiceContract;
use App\Modules\ImageGateway\Application\Contracts\OperatorStudyQuery;
use App\Modules\ImageGateway\Application\Services\ImageGatewayAiService;
use App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Modules\ImageGateway\Domain\Security\UntrustedImagePolicy;
use App\Modules\ImageGateway\Infrastructure\AiPacsClient;
use App\Shared\Security\KeyMaterial;
use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class ImageGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Image Gateway');
        $this->app->scoped(OperatorStudyQuery::class, ImageGatewayCaptureService::class);
        $this->app->scoped(ImageGatewayAiServiceContract::class, ImageGatewayAiService::class);
        $this->app->scoped(AiPacsAdapterContract::class, AiPacsClient::class);
        $this->app->singleton(UntrustedImagePolicy::class, fn (): UntrustedImagePolicy => UntrustedImagePolicy::fromConfig(config('mhcs.image_policy')),
        );
        $this->app->singleton(ManifestSigner::class, function (): ManifestSigner {
            $keyId = config('mhcs.security.manifest_key_id');

            if (! is_string($keyId) || trim($keyId) === '') {
                throw new \RuntimeException('The manifest key ID is not configured.');
            }

            return new ManifestSigner([
                $keyId => KeyMaterial::fromConfig(config('mhcs.security.manifest_key')),
            ]);
        });
    }
}
