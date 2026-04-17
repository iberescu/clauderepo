<?php

namespace App\Providers;

use App\Services\Contracts\CandidateDiscoveryServiceInterface;
use App\Services\Contracts\GeoSearchServiceInterface;
use App\Services\Contracts\SolarApiServiceInterface;
use App\Services\Fake\FakeCandidateDiscoveryService;
use App\Services\Fake\FakeGeoSearchService;
use App\Services\Fake\FakeSolarApiService;
use App\Services\Google\GoogleCandidateDiscoveryService;
use App\Services\Google\GoogleGeoSearchService;
use App\Services\Google\GoogleSolarApiService;
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

        $this->app->bind(SolarApiServiceInterface::class, $useFakes
            ? FakeSolarApiService::class
            : GoogleSolarApiService::class);
    }

    public function boot(): void
    {
    }
}
