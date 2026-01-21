<?php

namespace Skylark\Freesend\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Skylark\Freesend\FreesendServiceProvider;

/**
 * Base test case for package tests.
 *
 * Uses Orchestra Testbench to provide a Laravel application
 * context for testing the package.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get the package providers to register.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [FreesendServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $app["config"]->set("freesend.api_key", "test-api-key");
        $app["config"]->set(
            "freesend.endpoint",
            "https://freesend.test/api/send-email",
        );

        $app["config"]->set("mail.default", "freesend");
        $app["config"]->set("mail.mailers.freesend", [
            "transport" => "freesend",
        ]);

        $app["config"]->set("mail.from", [
            "address" => "test@example.com",
            "name" => "Test Sender",
        ]);
    }
}
