<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway;

use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Modules\ImageGateway\Domain\Security\UntrustedImagePolicy;
use App\Shared\Security\KeyMaterial;
use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class ImageGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Image Gateway');
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
