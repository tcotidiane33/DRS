<?php

use App\Http\Controllers\VmController;
use App\Http\Controllers\CephMirroringController;
use App\Http\Controllers\CephMirrorStepController;
use Illuminate\Support\Facades\Route;

Route::prefix('mirroring')->name('mirroring.')->group(function () {
    Route::get('/', [CephMirroringController::class, 'index'])->name('index');
    Route::post('/setup', [CephMirroringController::class, 'setup'])->name('setup');
    Route::post('/failover', [CephMirroringController::class, 'failover'])->name('failover');
    // Redirect GET requests (e.g. browser refresh after old form submit) back to dashboard
    Route::get('/setup', fn() => redirect()->route('mirroring.index'));
    Route::get('/failover', fn() => redirect()->route('mirroring.index'));
    Route::get('/logs', [CephMirroringController::class, 'listLogs'])->name('logs');
    Route::get('/logs/{logId}', [CephMirroringController::class, 'getLog'])->name('logs.show');
    Route::get('/logs/{logId}/stream', [CephMirroringController::class, 'streamLog'])->name('logs.stream');
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

    // ── Ceph Mirroring Step-by-Step API ──────────────────────────────────
    Route::prefix('mirroring/steps')->name('mirroring.steps.')->group(function () {
        // Setup steps
        Route::post('/create-user',      [CephMirrorStepController::class, 'createUser'])->name('create-user');
        Route::post('/transfer-keyring', [CephMirrorStepController::class, 'transferKeyring'])->name('transfer-keyring');
        Route::post('/configure-site-b', [CephMirrorStepController::class, 'configureSiteB'])->name('configure-site-b');
        Route::post('/enable-pool',      [CephMirrorStepController::class, 'enablePool'])->name('enable-pool');
        Route::post('/configure-peer',   [CephMirrorStepController::class, 'configurePeer'])->name('configure-peer');
        Route::post('/setup-daemon',     [CephMirrorStepController::class, 'setupDaemon'])->name('setup-daemon');
        Route::post('/enable-image',     [CephMirrorStepController::class, 'enableImage'])->name('enable-image');
        // Failover steps
        Route::post('/demote',           [CephMirrorStepController::class, 'demote'])->name('demote');
        Route::post('/promote',          [CephMirrorStepController::class, 'promote'])->name('promote');
        // Status
        Route::get('/status',            [CephMirrorStepController::class, 'poolStatus'])->name('status');
    });
});

