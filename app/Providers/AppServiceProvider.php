<?php

namespace App\Providers;

use App\Directory\CachedDirectoryService;
use App\Directory\DirectoryService;
use App\Directory\LdapDirectoryService;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DirectoryService::class, function ($app) {
            $config = config('directory');

            return new CachedDirectoryService(
                new LdapDirectoryService($config['ldap'], $config['groups']),
                $app->make(CacheRepository::class),
                (int) $config['cache_ttl'],
            );
        });
    }

    public function boot(): void
    {
        // Lazy Loading und fehlende Attribute fallen in Entwicklung und Tests sofort auf.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Rollen kommen aus den AD-Gruppen (siehe AuthenticateRemoteUser).
        Gate::define('admin', fn (User $user): bool => $user->is_admin);
        Gate::define('organizer', fn (User $user): bool => $user->is_organizer);
    }
}
