<?php

use Doppar\Ripple\Http\UpdateController;
use Doppar\Ripple\Http\UploadController;
use Doppar\Ripple\Support\Config;
use Phaseolies\Support\Facades\Route;

$update = Config::get('ripple.update_uri', '/ripple/update');
$lazy = Config::get('ripple.lazy_uri', '/ripple/lazy');
$upload = Config::get('ripple.upload_uri', '/ripple/upload');
$preview = Config::get('ripple.preview_uri', '/ripple/preview');

Route::post($update, [UpdateController::class, 'handle']);
Route::post($lazy, [UpdateController::class, 'lazy']);
Route::post($upload, [UploadController::class, 'upload']);
Route::get($preview, [UploadController::class, 'preview']);
