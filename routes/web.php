<?php

use App\Http\Controllers\VmController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/vms');

Route::prefix('vms')->name('vms.')->group(function () {
    Route::get('/', [VmController::class, 'index'])->name('index');
    Route::get('/create', [VmController::class, 'create'])->name('create');
    Route::post('/', [VmController::class, 'store'])->name('store');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/nodes', [VmController::class, 'apiNodes'])->name('nodes');
    Route::get('/best-node', [VmController::class, 'apiBestNode'])->name('best-node');
    Route::get('/templates', [VmController::class, 'apiTemplates'])->name('templates');
    Route::get('/jobs/{id}', [VmController::class, 'jobStatus'])->name('jobs.show');
});
