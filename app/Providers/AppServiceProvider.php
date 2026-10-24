<?php

namespace App\Providers;

use App\Directory\CachedDirectoryService;
use App\Directory\DirectoryService;
use App\Directory\LdapDirectoryService;
use App\Directory\StaticDirectoryService;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
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

            if ($config['driver'] === 'static') {
                if ($app->isProduction()) {
                    throw new \RuntimeException('DIRECTORY_DRIVER=static ist in Produktion nicht erlaubt.');
                }

                return new StaticDirectoryService($config['static']);
            }

            return new CachedDirectoryService(
                new LdapDirectoryService($config['ldap'], $config['groups']),
                $app->make(CacheRepository::class),
                (int) $config['cache_ttl'],
            );
        });
    }

    public function boot(): void
    {
        // Datumsangaben in deutscher Schreibweise (Wochentage, Monatsnamen).
        Carbon::setLocale(config('app.locale'));
        CarbonImmutable::setLocale(config('app.locale'));

        // Lazy Loading und fehlende Attribute fallen in Entwicklung und Tests sofort auf.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Rollen kommen aus den AD-Gruppen (siehe AuthenticateRemoteUser).
        Gate::define('admin', fn (User $user): bool => $user->is_admin);
        Gate::define('organizer', fn (User $user): bool => $user->is_organizer);
    }
}
