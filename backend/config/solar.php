<?php

// Runtime credentials live in config/secrets.php (or secrets.php.example for
// new checkouts) and win unconditionally over environment variables. This
// avoids the docker-compose env-override trap where `FAKE_PROVIDERS: true`
// silently shadowed a backend/.env edit.
$secretsFile = __DIR__.'/secrets.php';
$secrets = file_exists($secretsFile) ? require $secretsFile : require __DIR__.'/secrets.php.example';

return [

    /*
    |--------------------------------------------------------------------------
    | Provider toggles
    |--------------------------------------------------------------------------
    |
    | When `fake_providers` is true the app uses deterministic in-process
    | doubles for Google Maps, the Solar API and Gemini so the whole flow
    | runs offline. Flip the value in config/secrets.php to go live — no
    | environment variable needed.
    |
    */

    'fake_providers' => (bool) ($secrets['fake_providers'] ?? true),

    'google' => [
        'maps_api_key'  => (string) ($secrets['google_maps_api_key']  ?? ''),
        'solar_api_key' => (string) ($secrets['google_solar_api_key'] ?? ''),
        'solar_endpoint' => env(
            'GOOGLE_SOLAR_ENDPOINT',
            'https://solar.googleapis.com/v1',
        ),
        'geocoding_endpoint' => env(
            'GOOGLE_GEOCODING_ENDPOINT',
            'https://maps.googleapis.com/maps/api/geocode/json',
        ),
        'places_endpoint' => env(
            'GOOGLE_PLACES_ENDPOINT',
            'https://places.googleapis.com/v1/places:autocomplete',
        ),
    ],

    'gemini' => [
        'api_key' => (string) ($secrets['gemini_api_key'] ?? ''),
        'endpoint' => env(
            'GEMINI_ENDPOINT',
            'https://generativelanguage.googleapis.com/v1beta',
        ),
        'model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem roots
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'projects' => 'projects',
        'config'   => 'config',
    ],

    /*
    |--------------------------------------------------------------------------
    | Candidate discovery
    |--------------------------------------------------------------------------
    */

    'candidates' => [
        'max_count'            => 12,
        'spacing_meters'       => 18.0,
        'min_confidence'       => 0.35,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default editable assumptions
    |--------------------------------------------------------------------------
    |
    | Used when the corresponding config text file under storage/app/config
    | does not yet exist. ConfigFileService seeds the text file with these
    | values on first read.
    |
    */

    'defaults' => [

        'panel' => [
            'model'        => 'Generic 420W Monocrystalline',
            'width_m'      => 1.134,
            'height_m'     => 1.722,
            'watt_peak'    => 420,
            'efficiency'   => 0.205,
            'setback_m'    => 0.40,
            'row_gap_m'    => 0.02,
            'col_gap_m'    => 0.02,
        ],

        'pricing' => [
            'currency'            => 'EUR',
            'panel_price'         => 180.0,
            'inverter_per_kwp'    => 180.0,
            'mounting_per_panel'  => 55.0,
            'labor_per_panel'     => 90.0,
            'fixed_bos'           => 450.0,
            'roof_difficulty'     => 1.0,
            'margin_pct'          => 0.18,
            'discount_pct'        => 0.0,
            'vat_pct'             => 0.21,
        ],

        'savings' => [
            'grid_tariff_per_kwh'     => 0.32,
            'export_tariff_per_kwh'   => 0.08,
            'self_consumption_ratio'  => 0.55,
            'annual_inflation_pct'    => 0.03,
            'horizon_years'           => 25,
        ],

        'branding' => [
            'company_name'  => 'Helios Rooftops',
            'tagline'       => 'Photovoltaic proposals in minutes.',
            'contact_email' => 'sales@example.com',
            'contact_phone' => '+00 000 000 000',
            'website'       => 'https://example.com',
            'address'       => 'Sample Street 1, Example City',
            'proposal_expiry_days' => 30,
            'disclaimer' => 'This proposal is a preliminary sales estimate based on public aerial imagery and modelled irradiance. Final engineering requires a site survey.',
        ],

    ],

];
