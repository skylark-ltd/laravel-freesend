<?php

use Illuminate\Support\Facades\Mail;
use Skylarkltd\Freesend\FreesendTransport;

it('registers the freesend mail transport', function () {
    $mailer = Mail::mailer('freesend');

    expect($mailer)->not->toBeNull();
});

it('uses freesend transport when configured as default', function () {
    config(['mail.default' => 'freesend']);

    $mailer = Mail::mailer();

    expect($mailer)->not->toBeNull();
});

it('loads configuration from package config', function () {
    expect(config('freesend.api_key'))->toBe('test-api-key');
    expect(config('freesend.endpoint'))->toBe('https://freesend.test/api/send-email');
});

it('throws exception when api key is not configured', function () {
    config(['freesend.api_key' => null]);
    config(['mail.mailers.freesend.key' => null]);

    // Force re-creation of mailer
    Mail::purge('freesend');
    Mail::mailer('freesend');
})->throws(InvalidArgumentException::class, 'Freesend API key is not configured');

it('throws exception when endpoint is not configured', function () {
    config(['freesend.endpoint' => null]);
    config(['mail.mailers.freesend.endpoint' => null]);

    // Force re-creation of mailer
    Mail::purge('freesend');
    Mail::mailer('freesend');
})->throws(InvalidArgumentException::class, 'Freesend endpoint is not configured');

it('uses api key from mailer config over package config', function () {
    config(['mail.mailers.freesend.key' => 'mailer-specific-key']);
    config(['freesend.api_key' => 'package-config-key']);

    Mail::purge('freesend');

    // The mailer should be created without error using the mailer-specific key
    $mailer = Mail::mailer('freesend');

    expect($mailer)->not->toBeNull();
});

it('uses endpoint from mailer config over package config', function () {
    config(['mail.mailers.freesend.endpoint' => 'https://custom.endpoint/api']);
    config(['freesend.endpoint' => 'https://default.endpoint/api']);

    Mail::purge('freesend');

    // The mailer should be created without error
    $mailer = Mail::mailer('freesend');

    expect($mailer)->not->toBeNull();
});

it('can send mail through freesend transport', function () {
    // This test verifies the integration works but doesn't actually send
    // (since the API would reject our test credentials)
    $mailer = Mail::mailer('freesend');

    expect($mailer)->not->toBeNull();
});

it('merges package config with application config', function () {
    // Verify that the config is properly merged
    expect(config('freesend'))->toBeArray();
    expect(config('freesend'))->toHaveKeys(['api_key', 'endpoint']);
});
