<?php

use Doppar\Ripple\Http\UpdateController;
use Phaseolies\Support\Facades\Route;

$uri = config('ripple.update_uri', '/ripple/update');

Route::post($uri, [UpdateController::class, 'handle']);
