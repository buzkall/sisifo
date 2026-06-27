<?php

use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Notifications\Channels\DatabaseNotificationChannel;
use Arzcode\Sisifo\Tests\Support\TestUser;
use Illuminate\Notifications\DatabaseNotification;

use function Pest\Laravel\assertDatabaseCount;

test('resolves the recipient from notify_user_id without a closure (cache-safe)', function() {
    $user = TestUser::factory()->create();

    config()->set('auth.providers.users.model', TestUser::class);
    config()->set('sisifo.notify_user_id', $user->id);
    config()->set('sisifo.notifications.notifiable', null);

    app(DatabaseNotificationChannel::class)->send(MailboxTask::factory()->create(), 'Title', 'Body', false);

    assertDatabaseCount(DatabaseNotification::class, 1);
});

test('honours an explicit notifiable_model over the auth model', function() {
    $user = TestUser::factory()->create();

    config()->set('auth.providers.users.model', null);
    config()->set('sisifo.notifications.notifiable_model', TestUser::class);
    config()->set('sisifo.notify_user_id', $user->id);
    config()->set('sisifo.notifications.notifiable', null);

    app(DatabaseNotificationChannel::class)->send(MailboxTask::factory()->create(), 'Title', 'Body', false);

    assertDatabaseCount(DatabaseNotification::class, 1);
});

test('sends nothing when no recipient can be resolved', function() {
    config()->set('sisifo.notify_user_id', null);
    config()->set('sisifo.notifications.notifiable', null);

    app(DatabaseNotificationChannel::class)->send(MailboxTask::factory()->create(), 'Title', 'Body', false);

    assertDatabaseCount(DatabaseNotification::class, 0);
});

test('still supports a closure resolver for backward compatibility', function() {
    $user = TestUser::factory()->create();

    config()->set('sisifo.notifications.notifiable', fn() => TestUser::find($user->id));

    app(DatabaseNotificationChannel::class)->send(MailboxTask::factory()->create(), 'Title', 'Body', false);

    assertDatabaseCount(DatabaseNotification::class, 1);
});
