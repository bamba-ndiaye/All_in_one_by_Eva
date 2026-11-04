<?php

namespace App\Providers;

use App\Models\User;
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

        // Chaque permission du catalogue devient un Gate : @can('clients.export')
        foreach (array_keys(config('permissions.catalogue')) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}