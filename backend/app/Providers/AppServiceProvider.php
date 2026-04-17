<?php

namespace App\Providers;

use App\Services\Contracts\CandidateDiscoveryServiceInterface;
use App\Services\Contracts\GeoSearchServiceInterface;
use App\Services\Fake\FakeCandidateDiscoveryService;
use App\Services\Fake\FakeGeoSearchService;
use App\Services\Google\GoogleCandidateDiscoveryService;
use App\Services\Google\GoogleGeoSearchService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $useFakes = (bool) config('solar.fake_providers', true);

        $this->app->bind(GeoSearchServiceInterface::class, $useFakes
            ? FakeGeoSearchService::class
            : GoogleGeoSearchService::class);

        $this->app->bind(CandidateDiscoveryServiceInterface::class, $useFakes
            ? FakeCandidateDiscoveryService::class
            : GoogleCandidateDiscoveryService::class);
    }

    public function boot(): void
    {
    }
}
