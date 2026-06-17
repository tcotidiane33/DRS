<?php

namespace App\Providers;

use App\Services\NodeSelectorService;
use App\Services\ProxmoxService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProxmoxService::class);
        $this->app->singleton(NodeSelectorService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
