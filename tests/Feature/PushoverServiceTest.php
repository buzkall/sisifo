<?php

use Arzcode\Sisifo\Services\Pushover\PushoverService;
use Illuminate\Support\Facades\Http;

it('sends a pushover notification successfully', function() {
    Http::fake([
        'api.pushover.net/*' => Http::response(['status' => 1], 200),
    ]);

    config([
        'services.pushover.token'    => 'test-token',
        'services.pushover.user_key' => 'test-user',
    ]);

    $service = new PushoverService;
    $service->send('Test message', 'Test title');

    Http::assertSent(function($request) {
        return $request->url() === 'https://api.pushover.net/1/messages.json'
            && $request['token'] === 'test-token'
            && $request['user'] === 'test-user'
            && $request['message'] === 'Test message'
            && $request['title'] === 'Test title'
            && $request['html'] === 1;
    });
});

it('throws exception on pushover failure', function() {
    Http::fake([
        'api.pushover.net/*' => Http::response(['status' => 0, 'errors' => ['invalid token']], 400),
    ]);

    config([
        'services.pushover.token'    => 'invalid-token',
        'services.pushover.user_key' => 'test-user',
    ]);

    $service = new PushoverService;
    $service->send('Test message');
})->throws(RuntimeException::class);
