<?php

namespace BioHiveTech\Introspection;

use Illuminate\Auth\RequestGuard;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Config\Repository as Config;
use BioHiveTech\Introspection\Introspection;
use BioHiveTech\Introspection\IntrospectionGuard;

class IntrospectionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/introspection.php', 'introspection'
        );
        
        $this->registerGuard();
    }

    protected function registerGuard()
    {
        Auth::resolved(function ($auth) {
            $auth->extend('introspect', function ($app, $name, array $config) {
                return tap($this->makeGuard($config), function ($guard) {
                    $this->app->refresh('request', $guard, 'setRequest');
                });
            });
        });
    }

    protected function makeGuard($config)
    {
        return new RequestGuard(function ($request) use ($config) {
            return (new IntrospectionGuard(
                Auth::createUserProvider($config['provider']),
                $this->app->make('encrypter')
            ))->user($request);
        }, $this->app['request']);
    }

    /**
     * Register the cookie deletion event handler.
     *
     * @return void
     */
    protected function deleteCookieOnLogout()
    {
        Event::listen(Logout::class, function () {
            if (Request::hasCookie(Introspection::cookie())) {
                Cookie::queue(Cookie::forget(Introspection::cookie()));
            }
        });
    }
}
