<?php

namespace App\Providers;

use App\Directory\CachedDirectoryService;
use App\Directory\DirectoryService;
use App\Directory\LdapDirectoryService;
use App\Directory\StaticDirectoryService;
use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\BookingMoved;
use App\Events\OfferCancelled;
use App\Events\OfferChanged;
use App\Events\WishesReleased;
use App\Listeners\BookingNotifications;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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

        // E-Mails zu Buchungen und Änderungen (Warteschlange, siehe routes/console.php).
        Event::listen(BookingConfirmed::class, [BookingNotifications::class, 'bookingConfirmed']);
        Event::listen(BookingCancelled::class, [BookingNotifications::class, 'bookingCancelled']);
        Event::listen(BookingMoved::class, [BookingNotifications::class, 'bookingMoved']);
        Event::listen(OfferChanged::class, [BookingNotifications::class, 'offerChanged']);
        Event::listen(OfferCancelled::class, [BookingNotifications::class, 'offerCancelled']);
        Event::listen(WishesReleased::class, [BookingNotifications::class, 'wishesReleased']);

        // Rollen kommen aus den AD-Gruppen (siehe AuthenticateRemoteUser).
        Gate::define('admin', fn (User $user): bool => $user->is_admin);
        Gate::define('organizer', fn (User $user): bool => $user->is_organizer);
    }
}
