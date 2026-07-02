<?php

namespace Tareef\Laravel;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class TareefServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tareef.php', 'tareef');

        $this->app->singleton(TareefClient::class, function ($app) {
            $cfg = $app['config']->get('tareef');

            return new TareefClient(
                http: $app->make(HttpFactory::class),
                apiKey: (string) ($cfg['api_key'] ?? ''),
                baseUrl: (string) ($cfg['base_url'] ?? 'https://tareef.io'),
                timeout: (int) ($cfg['timeout'] ?? 30),
                retries: (int) ($cfg['retries'] ?? 2),
                throwOnQuota: (bool) ($cfg['throw_on_quota'] ?? true),
            );
        });

        // Short binding so the facade can resolve via 'tareef'.
        $this->app->alias(TareefClient::class, 'tareef');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tareef.php' => config_path('tareef.php'),
            ], 'tareef-config');
        }
    }

    public function provides(): array
    {
        return [TareefClient::class, 'tareef'];
    }
}
