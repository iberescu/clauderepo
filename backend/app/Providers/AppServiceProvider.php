<?php

namespace App\Providers;

use App\Services\Contracts\CandidateDiscoveryServiceInterface;
use App\Services\Contracts\GeminiImageServiceInterface;
use App\Services\Contracts\GeoSearchServiceInterface;
use App\Services\Contracts\SolarApiServiceInterface;
use App\Services\Contracts\StaticMapsServiceInterface;
use App\Services\Fake\FakeCandidateDiscoveryService;
use App\Services\Fake\FakeGeminiImageService;
use App\Services\Fake\FakeGeoSearchService;
use App\Services\Fake\FakeSolarApiService;
use App\Services\Fake\FakeStaticMapsService;
use App\Services\Google\GoogleCandidateDiscoveryService;
use App\Services\Google\GoogleGeminiImageService;
use App\Services\Google\GoogleGeoSearchService;
use App\Services\Google\GoogleSolarApiService;
use App\Services\Google\GoogleStaticMapsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Re-read `solar.fake_providers` at resolve time so tests that flip
        // the flag in setUp() get the right implementation, and a
        // config/secrets.php edit does not require a rebuild of the
        // service container.
        $this->bindProvider(GeoSearchServiceInterface::class, FakeGeoSearchService::class, GoogleGeoSearchService::class);
        $this->bindProvider(CandidateDiscoveryServiceInterface::class, FakeCandidateDiscoveryService::class, GoogleCandidateDiscoveryService::class);
        $this->bindProvider(SolarApiServiceInterface::class, FakeSolarApiService::class, GoogleSolarApiService::class);
        $this->bindProvider(GeminiImageServiceInterface::class, FakeGeminiImageService::class, GoogleGeminiImageService::class);
        $this->bindProvider(StaticMapsServiceInterface::class, FakeStaticMapsService::class, GoogleStaticMapsService::class);
    }

    private function bindProvider(string $interface, string $fake, string $live): void
    {
        $this->app->bind($interface, function ($app) use ($fake, $live) {
            $useFakes = (bool) config('solar.fake_providers', true);
            return $app->make($useFakes ? $fake : $live);
        });
    }

    public function boot(): void
    {
    }
}
