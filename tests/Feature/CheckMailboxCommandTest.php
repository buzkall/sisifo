<?php

use Arzcode\Sisifo\Models\InboundEmail;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\MessageCollection;

it('fetches and saves unseen emails', function() {
    $message = Mockery::mock(Message::class);
    $message->shouldReceive('getMessageId')->andReturn('test-message-id@example.com');
    $message->shouldReceive('getSubject')->andReturn('Test Subject');
    $message->shouldReceive('getFrom')->andReturn([
        (object)['mail' => 'sender@example.com', 'personal' => 'Test Sender'],
    ]);
    $message->shouldReceive('getRawBody')->andReturn('Raw email body');
    $message->shouldReceive('getTextBody')->andReturn('Plain text body');
    $message->shouldReceive('getDate')->andReturn(now());

    $messages = new MessageCollection([$message]);

    $whereQuery = Mockery::mock(WhereQuery::class);
    $whereQuery->shouldReceive('whereUnseen')->andReturnSelf();
    $whereQuery->shouldReceive('whereSince')->andReturnSelf();
    $whereQuery->shouldReceive('get')->andReturn($messages);

    $folder = Mockery::mock(Folder::class);
    $folder->shouldReceive('messages')->andReturn($whereQuery);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('getFolder')->with('INBOX')->andReturn($folder);

    $clientManager = Mockery::mock(ClientManager::class);
    $clientManager->shouldReceive('make')->with(Mockery::type('array'))->andReturn($client);

    $this->app->instance(ClientManager::class, $clientManager);

    Cache::forget('mailbox:last_fetch');

    $this->artisan('mailbox:process')->assertSuccessful();

    expect(InboundEmail::count())->toBe(1);
    expect(InboundEmail::first())
        ->message_id->toBe('test-message-id@example.com')
        ->subject->toBe('Test Subject')
        ->from_address->toBe('sender@example.com')
        ->from_name->toBe('Test Sender')
        ->text_body->toBe('Plain text body');
});

it('does not duplicate emails with the same message_id', function() {
    InboundEmail::factory()->create(['message_id' => 'existing@example.com']);

    expect(InboundEmail::count())->toBe(1);

    InboundEmail::firstOrCreate(
        ['message_id' => 'existing@example.com'],
        [
            'subject'      => 'Duplicate',
            'from_address' => 'test@example.com',
            'from_name'    => 'Test',
            'text_body'    => 'Body',
            'received_at'  => now(),
        ]
    );

    expect(InboundEmail::count())->toBe(1);
});

it('runs without errors when no emails are found', function() {
    $messages = new MessageCollection([]);

    $whereQuery = Mockery::mock(WhereQuery::class);
    $whereQuery->shouldReceive('whereUnseen')->andReturnSelf();
    $whereQuery->shouldReceive('whereSince')->andReturnSelf();
    $whereQuery->shouldReceive('get')->andReturn($messages);

    $folder = Mockery::mock(Folder::class);
    $folder->shouldReceive('messages')->andReturn($whereQuery);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('getFolder')->with('INBOX')->andReturn($folder);

    $clientManager = Mockery::mock(ClientManager::class);
    $clientManager->shouldReceive('make')->with(Mockery::type('array'))->andReturn($client);

    $this->app->instance(ClientManager::class, $clientManager);

    Cache::forget('mailbox:last_fetch');

    $this->artisan('mailbox:process')->assertSuccessful();

    expect(InboundEmail::count())->toBe(0);
});
