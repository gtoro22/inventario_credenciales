<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\SecretVault;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SecretVault::class, fn () => SecretVault::fromConfig());
    }

    public function boot(): void
    {
        // El superadministrador pasa cualquier Gate/Policy.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Cada permiso base es también un Gate: Gate::allows('credentials.reveal').
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission->value));
        }

        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());
    }
}
