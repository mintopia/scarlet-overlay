<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->bindImageManager();
    }

    protected function bindImageManager(): void
    {
        $this->app->bind(ImageManager::class, function () {
            return new ImageManager(new Driver);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // Catalog mutation is restricted to owners — distinct from generic admin access.
        Gate::define('manageCanonicalCatalog', fn (User $user): bool => $user->isOwner());
    }
}
