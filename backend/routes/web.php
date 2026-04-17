<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => config('app.name'),
    'status'  => 'ok',
    'docs'    => '/api/v1/health',
]));
