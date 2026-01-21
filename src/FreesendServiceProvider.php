<?php

namespace Skylark\Freesend;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Laravel service provider for the Freesend mail transport.
 *
 * This provider registers the Freesend transport with Laravel's mail system,
 * allowing emails to be sent through the Freesend API using Laravel's
 * standard Mail facade and Mailable classes.
 *
 * @see https://freesend.metafog.io/docs/api/send-email
 */
class FreesendServiceProvider extends ServiceProvider
{
    /**
     * Register the Freesend configuration.
     *
     * Merges the package configuration with the application's config,
     * making Freesend settings available via config('freesend').
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . "/../config/freesend.php", "freesend");
    }

    /**
     * Bootstrap the Freesend mail transport.
     *
     * Publishes the configuration file and registers the 'freesend'
     * transport with Laravel's mail system.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__ . "/../config/freesend.php" => config_path(
                        "freesend.php",
                    ),
                ],
                "freesend-config",
            );
        }

        $this->registerTransport();
    }

    /**
     * Register the Freesend mail transport with Laravel.
     *
     * Extends Laravel's mail system with a 'freesend' transport that can be
     * configured in config/mail.php. The transport accepts the following
     * configuration options:
     *
     * - key: The Freesend API key (falls back to config('freesend.api_key'))
     * - endpoint: The API endpoint URL (falls back to config('freesend.endpoint'))
     *
     * @return void
     *
     * @throws InvalidArgumentException If the API key or endpoint is not configured.
     */
    protected function registerTransport(): void
    {
        Mail::extend("freesend", function (array $config = []) {
            $apiKey = $config["key"] ?? config("freesend.api_key");
            $endpoint = $config["endpoint"] ?? config("freesend.endpoint");

            if (empty($apiKey)) {
                throw new InvalidArgumentException(
                    "Freesend API key is not configured.",
                );
            }

            if (empty($endpoint)) {
                throw new InvalidArgumentException(
                    "Freesend endpoint is not configured.",
                );
            }

            return new FreesendTransport($apiKey, $endpoint);
        });
    }
}
