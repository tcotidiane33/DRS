<?php

use App\Http\Controllers\VmController;
use App\Http\Controllers\CephMirroringController;
use Illuminate\Support\Facades\Route;

Route::prefix('mirroring')->name('mirroring.')->group(function () {
    Route::get('/', [CephMirroringController::class, 'index'])->name('index');
    Route::post('/setup', [CephMirroringController::class, 'setup'])->name('setup');
    Route::post('/failover', [CephMirroringController::class, 'failover'])->name('failover');
});


Route::redirect('/', '/vms');

Route::prefix('vms')->name('vms.')->group(function () {
    Route::get('/', [VmController::class, 'index'])->name('index');
    Route::get('/create', [VmController::class, 'create'])->name('create');
    Route::get('/best-node', [VmController::class, 'apiBestNode'])->name('best-node');
    Route::get('/templates', [VmController::class, 'apiTemplates'])->name('templates');
    Route::get('/storages', [VmController::class, 'apiStorages'])->name('storages');
    Route::get('/{job}/edit', [VmController::class, 'edit'])->name('edit');
    Route::post('/{job}/retry', [VmController::class, 'retry'])->name('retry');
    Route::put('/{job}', [VmController::class, 'update'])->name('update');
    Route::post('/', [VmController::class, 'store'])->name('store');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/nodes', [VmController::class, 'apiNodes'])->name('nodes');
    Route::get('/best-node', [VmController::class, 'apiBestNode'])->name('best-node');
    Route::get('/templates', [VmController::class, 'apiTemplates'])->name('templates');
    Route::get('/isos', [VmController::class, 'apiIsos'])->name('isos');
    Route::get('/jobs/{id}', [VmController::class, 'jobStatus'])->name('jobs.show');
});
