<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ParametreService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // L'administratrice active a accès à tout.
        Gate::before(function (User $user) {
            return ($user->is_active && $user->isAdmin()) ? true : null;
        });

        // Réglages de l'écran « Paramètres » : ils remplacent les valeurs du .env.
        $this->app->make(ParametreService::class)->appliquer();

        // Chaque permission du catalogue devient un Gate : @can('clients.export')
        foreach (array_keys(config('permissions.catalogue')) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}
