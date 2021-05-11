<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Mezatsong\SwaggerDocs\Http\Controllers\SwaggerController;

if (Config::get('swagger.enable', true)) {
    Route::prefix(config('swagger.path', '/documentation'))->group(static function() {
        Route::get('', [SwaggerController::class, 'api']);
        Route::get('content', [SwaggerController::class, 'documentation']);
    });
}
