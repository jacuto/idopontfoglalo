<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use App\Services\Tenant;
final class TenantServiceProvider extends ServiceProvider
{
    public function boot(): void { app(\App\Services\PlatformConnections::class)->apply(); }
    public function register(): void { $this->app->scoped(Tenant::class, fn() => new Tenant()); }
}
