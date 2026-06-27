<?php

use Arzcode\Sisifo\Console\Commands\ProcessMailbox;
use Webklex\PHPIMAP\Message;

function callExtractTextBody(Message $message): string
{
    $command = new ProcessMailbox;
    $reflection = new ReflectionMethod($command, 'extractTextBody');

    return $reflection->invoke($command, $message);
}

function buildMimeMessage(string $headers, string $body): Message
{
    $raw = $headers . "\r\n\r\n" . $body;

    return Message::fromString($raw);
}

it('returns plain text part when present', function() {
    $message = buildMimeMessage(
        "From: test@example.com\r\n"
        . "Subject: hi\r\n"
        . 'Content-Type: text/plain; charset=utf-8',
        'Hello plain world'
    );

    expect(callExtractTextBody($message))->toBe('Hello plain world');
});

it('falls back to converted HTML when plain text is missing', function() {
    $html = '<html><head><style>body { color: red; font-family: Arial; }</style></head>'
        . '<body><h1>Title</h1><p>Hello <strong>world</strong></p></body></html>';

    $message = buildMimeMessage(
        "From: test@example.com\r\n"
        . "Subject: hi\r\n"
        . 'Content-Type: text/html; charset=utf-8',
        $html
    );

    $result = callExtractTextBody($message);

    expect($result)
        ->toContain('Hello')
        ->toContain('world')
        ->toContain('Title')
        ->not->toContain('color: red')
        ->not->toContain('font-family');
});

it('returns empty string when the email has no body', function() {
    $message = buildMimeMessage(
        "From: test@example.com\r\n"
        . "Subject: empty\r\n"
        . 'Content-Type: text/plain; charset=utf-8',
        ''
    );

    expect(callExtractTextBody($message))->toBe('');
});
