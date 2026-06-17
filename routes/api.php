<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VmController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/nodes', [VmController::class, 'apiNodes']);
    Route::get('/best-node', [VmController::class, 'apiBestNode']);
    Route::get('/templates', [VmController::class, 'apiTemplates']);
    Route::get('/storages', [VmController::class, 'apiStorages']);
    Route::get('/jobs', [VmController::class, 'jobs']);
    Route::post('/vms', [VmController::class, 'store']);
    Route::get('/jobs/{id}', [VmController::class, 'jobStatus']);
});
