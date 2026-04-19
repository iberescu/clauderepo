<?php

/*
|--------------------------------------------------------------------------
| Runtime credentials
|--------------------------------------------------------------------------
|
| config/solar.php reads these values directly (via require) so they win
| unconditionally over .env and docker-compose `environment:` entries.
| Edit this file to flip between fake and live providers — no env vars
| needed.
|
*/

return [
    'fake_providers'        => true,

    'google_maps_api_key'   => '',
    'google_solar_api_key'  => '',
    'gemini_api_key'        => '',
];
