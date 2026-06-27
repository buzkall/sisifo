<?php

namespace Arzcode\Sisifo\Services\Pushover;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PushoverService
{
    public function send(string $message, string $title = '', int $priority = 0): void
    {
        $message = str_replace('\n', "\n", $message);

        $response = Http::post('https://api.pushover.net/1/messages.json', [
            'token'    => config('services.pushover.token'),
            'user'     => config('services.pushover.user_key'),
            'message'  => $message,
            'title'    => $title,
            'priority' => $priority,
            'html'     => 1,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(__('sisifo::sisifo.pushover_error', [
                'error' => $response->body(),
            ]));
        }
    }
}
