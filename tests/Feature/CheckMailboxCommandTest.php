<?php

use Arzcode\Sisifo\Models\InboundEmail;
use Arzcode\Sisifo\Models\MailboxTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\MessageCollection;

/**
 * The transient failure webklex/php-imap reports as a bare "failed to
 * authenticate": the socket died mid-LOGIN, so the real cause only lives in
 * the previous exception.
 */
function transientAuthFailure(): AuthFailedException
{
    return new AuthFailedException('failed to authenticate', 0, new RuntimeException('empty response'));
}

function fakeInboxClient(): Client
{
    $whereQuery = Mockery::mock(WhereQuery::class);
    $whereQuery->shouldReceive('whereUnseen')->andReturnSelf();
    $whereQuery->shouldReceive('whereSince')->andReturnSelf();
    $whereQuery->shouldReceive('get')->andReturn(new MessageCollection([]));

    $folder = Mockery::mock(Folder::class);
    $folder->shouldReceive('messages')->andReturn($whereQuery);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getFolder')->with('INBOX')->andReturn($folder);

    return $client;
}

function bindClientManager(Client $client): void
{
    $clientManager = Mockery::mock(ClientManager::class);
    $clientManager->shouldReceive('make')->with(Mockery::type('array'))->andReturn($client);

    app()->instance(ClientManager::class, $clientManager);

    Cache::forget('mailbox:last_fetch');
}

it('fetches and saves unseen emails', function() {
    $message = Mockery::mock(Message::class);
    $message->shouldReceive('getMessageId')->andReturn('test-message-id@example.com');
    $message->shouldReceive('getSubject')->andReturn('Test Subject');
    $message->shouldReceive('getFrom')->andReturn(new Attribute('from', [
        new Address((object)['mail' => 'sender@example.com', 'personal' => 'Test Sender']),
    ]));
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
    $client->shouldReceive('disconnect')->once();
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
    $client->shouldReceive('disconnect')->once();
    $client->shouldReceive('getFolder')->with('INBOX')->andReturn($folder);

    $clientManager = Mockery::mock(ClientManager::class);
    $clientManager->shouldReceive('make')->with(Mockery::type('array'))->andReturn($client);

    $this->app->instance(ClientManager::class, $clientManager);

    Cache::forget('mailbox:last_fetch');

    $this->artisan('mailbox:process')->assertSuccessful();

    expect(InboundEmail::count())->toBe(0);
});

it('passes the configured socket timeout to the IMAP client', function() {
    config()->set('sisifo.imap.timeout', 90);

    $client = fakeInboxClient();
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('disconnect')->once();

    $clientManager = Mockery::mock(ClientManager::class);
    $clientManager->shouldReceive('make')
        ->once()
        ->with(Mockery::on(fn(array $config): bool => $config['timeout'] === 90))
        ->andReturn($client);

    $this->app->instance(ClientManager::class, $clientManager);

    Cache::forget('mailbox:last_fetch');

    $this->artisan('mailbox:process')->assertSuccessful();
});

it('retries a transient connection failure and succeeds', function() {
    Sleep::fake();

    config()->set('sisifo.imap.retry_attempts', 3);

    $client = fakeInboxClient();
    $attempt = 0;
    $client->shouldReceive('connect')
        ->twice()
        ->andReturnUsing(function() use (&$attempt, $client) {
            $attempt++;

            if ($attempt === 1) {
                throw transientAuthFailure();
            }

            return $client;
        });
    $client->shouldReceive('disconnect')->twice();

    bindClientManager($client);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($attempt)->toBe(2);
});

it('logs the underlying cause when every retry fails', function() {
    Sleep::fake();
    Log::spy();

    config()->set('sisifo.imap.retry_attempts', 3);

    $client = fakeInboxClient();
    $client->shouldReceive('connect')->times(3)->andThrow(transientAuthFailure());
    $client->shouldReceive('disconnect')->times(3);

    bindClientManager($client);

    $this->artisan('mailbox:process')->assertSuccessful();

    Log::shouldHaveReceived('error')
        ->withArgs(fn(string $message): bool => str_contains($message, 'failed to authenticate')
            && str_contains($message, 'empty response'))
        ->once();
});

it('arms the fetch throttle even when the check fails', function() {
    Sleep::fake();

    config()->set('sisifo.imap.retry_attempts', 1);

    $client = fakeInboxClient();
    $client->shouldReceive('connect')->once()->andThrow(transientAuthFailure());
    $client->shouldReceive('disconnect')->once();

    bindClientManager($client);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect(Cache::get('mailbox:last_fetch'))->not->toBeNull();
});

it('does not retry when the server rejects the login', function() {
    Sleep::fake();

    config()->set('sisifo.imap.retry_attempts', 3);

    $client = fakeInboxClient();
    $client->shouldReceive('connect')
        ->once()
        ->andThrow(new ImapServerErrorException('NO [AUTHENTICATIONFAILED] Authentication failed'));
    $client->shouldReceive('disconnect')->once();

    bindClientManager($client);

    $this->artisan('mailbox:process')->assertSuccessful();

    Sleep::assertNeverSlept();
});

/**
 * A client whose first INBOX query (the fetch) finds nothing and whose second
 * (the read-state sync) reports the given message ids as still unseen.
 *
 * @param  array<int, string>  $unseenIds
 */
function fakeReadStateClient(array $unseenIds): Client
{
    $unseen = array_map(function(string $id) {
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('getMessageId')->andReturn($id);

        return $message;
    }, $unseenIds);

    $whereQuery = Mockery::mock(WhereQuery::class);
    $whereQuery->shouldReceive('whereUnseen')->andReturnSelf();
    $whereQuery->shouldReceive('whereSince')->andReturnSelf();
    $whereQuery->shouldReceive('setFetchBody')->once()->with(false)->andReturnSelf();
    $whereQuery->shouldReceive('get')->twice()->andReturn(new MessageCollection([]), new MessageCollection($unseen));

    $folder = Mockery::mock(Folder::class);
    $folder->shouldReceive('messages')->andReturn($whereQuery);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('disconnect')->once();
    $client->shouldReceive('getFolder')->with('INBOX')->andReturn($folder);

    return $client;
}

it('marks stored emails that are no longer unseen in the inbox as read', function() {
    MailboxTask::factory()->create(['filters' => ['ignore_read' => true], 'last_run_at' => now()]);

    $handled = InboundEmail::factory()->create(['message_id' => 'handled@example.com', 'received_at' => now()->subDay()]);
    $unread = InboundEmail::factory()->create(['message_id' => 'unread@example.com', 'received_at' => now()->subDay()]);
    $outOfWindow = InboundEmail::factory()->old()->create(['message_id' => 'old@example.com']);

    bindClientManager(fakeReadStateClient(['unread@example.com']));

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($handled->fresh()->read_at)->not->toBeNull()
        ->and($unread->fresh()->read_at)->toBeNull()
        ->and($outOfWindow->fresh()->read_at)->toBeNull();
});

it('clears the read mark when an email is unseen again', function() {
    MailboxTask::factory()->create(['filters' => ['ignore_read' => true], 'last_run_at' => now()]);

    $email = InboundEmail::factory()->create([
        'message_id'  => 'reopened@example.com',
        'received_at' => now()->subDay(),
        'read_at'     => now()->subHour(),
    ]);

    bindClientManager(fakeReadStateClient(['reopened@example.com']));

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($email->fresh()->read_at)->toBeNull();
});

it('does not query the read state when no active task ignores read emails', function() {
    MailboxTask::factory()->create(['filters' => ['ignore_read' => true], 'is_active' => false]);
    MailboxTask::factory()->create(['last_run_at' => now()]);

    $email = InboundEmail::factory()->create(['received_at' => now()->subDay()]);

    $client = fakeInboxClient();
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('disconnect')->once();

    bindClientManager($client);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($email->fresh()->read_at)->toBeNull();
});
