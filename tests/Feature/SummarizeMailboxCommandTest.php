<?php

use Arzcode\Sisifo\Contracts\LlmProvider;
use Arzcode\Sisifo\Enums\MailboxTaskNotificationEnum;
use Arzcode\Sisifo\Models\InboundEmail;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Services\Pushover\PushoverService;
use Arzcode\Sisifo\Settings\MailboxSettings;
use Arzcode\Sisifo\Tests\Support\FakeLlmProvider;
use Arzcode\Sisifo\Tests\Support\TestUser;
use Illuminate\Notifications\DatabaseNotification;

function fakeLlm(array|Closure|string $responses = []): FakeLlmProvider
{
    $fake = new FakeLlmProvider($responses);
    app()->instance(LlmProvider::class, $fake);

    return $fake;
}

beforeEach(function() {
    MailboxTask::query()->delete();
    Cache::put('mailbox:last_fetch', now()->toIso8601String(), now()->addHour());
});

it('processes a summary task and sends via pushover', function() {
    $task = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->count(2)->create(['received_at' => now()->subHours(2)]);

    $fake = fakeLlm(['Resumen: 2 correos recibidos hoy.']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')
        ->once()
        ->with('Resumen: 2 correos recibidos hoy.', $task->name, -1);
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->last_run_at)->not->toBeNull();
    expect($task->fresh()->processedEmails)->toHaveCount(2);

    $fake->assertPrompted(fn() => true);
});

it('skips when no unprocessed emails exist', function() {
    MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    $fake = fakeLlm();

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    $fake->assertNeverPrompted();
});

it('ignores already processed emails for a task', function() {
    $task = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    $processed = InboundEmail::factory()->create(['received_at' => now()->subHours(2)]);
    $task->processedEmails()->attach($processed->id, ['processed_at' => now()]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(['Un correo nuevo.']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->processedEmails)->toHaveCount(2);
});

it('ignores emails older than lookback days', function() {
    MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->old()->create();

    $fake = fakeLlm();

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    $fake->assertNeverPrompted();
});

it('filters emails by from_address', function() {
    $task = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
        'filters'            => ['from_addresses' => ['important@example.com']],
    ]);

    InboundEmail::factory()->create([
        'from_address' => 'other@example.com',
        'received_at'  => now()->subHour(),
    ]);

    InboundEmail::factory()->create([
        'from_address' => 'important@example.com',
        'received_at'  => now()->subHour(),
    ]);

    fakeLlm(['Correo importante.']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->processedEmails)->toHaveCount(1);
    expect($task->fresh()->processedEmails->first()->from_address)->toBe('important@example.com');
});

it('deactivates one-shot watch task after first match', function() {
    $task = MailboxTask::factory()->watch()->oneShot()->create();

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(['Alerta: correo recibido.']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->is_active)->toBeFalse();
});

it('skips inactive tasks', function() {
    MailboxTask::factory()->inactive()->create();

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    $fake = fakeLlm();

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    $fake->assertNeverPrompted();
});

it('treats empty filter arrays as no filter', function() {
    $task = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
        'filters'            => [
            'from_addresses'   => [],
            'from_domains'     => [],
            'subject_keywords' => [],
            'look_back_days'   => null,
        ],
    ]);

    InboundEmail::factory()->count(2)->create(['received_at' => now()->subHour()]);

    fakeLlm(['Resumen.']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->processedEmails)->toHaveCount(2);
});

it('sends error notification and stamps last_run_at when AI call fails', function() {
    $task = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(fn() => throw new RuntimeException('AI provider down'));

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')
        ->once()
        ->with(
            Mockery::on(fn(string $body): bool => str_contains($body, 'AI provider down')),
            Mockery::type('string'),
            1
        );
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->last_run_at)->not->toBeNull();
    expect($task->fresh()->processedEmails)->toHaveCount(0);
});

it('does not retry a daily task the same day after a failed attempt', function() {
    MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => now()->subMinutes(5),
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    $fake = fakeLlm();

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    $fake->assertNeverPrompted();
});

it('blends specific and common prompts when invoking the LLM', function() {
    $settings = app(MailboxSettings::class);
    $settings->common_prompt = 'BOILER';
    $settings->save();

    MailboxTask::factory()->create([
        'prompt'             => 'SPECIFIC',
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    $fake = fakeLlm(['ok']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    $fake->assertPrompted(fn($prompt) => str_starts_with($prompt->instructions, "SPECIFIC\n\nBOILER")
        && str_contains($prompt->instructions, 'NO_NOTIFY'));
});

it('sends only the specific prompt when common prompt is empty', function() {
    $settings = app(MailboxSettings::class);
    $settings->common_prompt = '';
    $settings->save();

    MailboxTask::factory()->create([
        'prompt'             => 'SPECIFIC',
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    $fake = fakeLlm(['ok']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    $fake->assertPrompted(fn($prompt) => str_starts_with($prompt->instructions, 'SPECIFIC')
        && ! str_contains($prompt->instructions, 'BOILER')
        && str_contains($prompt->instructions, 'NO_NOTIFY'));
});

it('dispatches every notification method configured on the task', function() {
    $user = TestUser::factory()->create();
    config()->set('sisifo.notify_user_id', $user->id);
    config()->set('sisifo.notifications.notifiable', fn() => TestUser::find($user->id));

    $task = MailboxTask::factory()->create([
        'schedule_frequency'   => 'daily',
        'schedule_time'        => '00:00',
        'last_run_at'          => null,
        'notification_methods' => [
            MailboxTaskNotificationEnum::Pushover,
            MailboxTaskNotificationEnum::Database,
        ],
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(['Resumen multi-canal.']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
    expect($task->fresh()->processedEmails)->toHaveCount(1);

    DatabaseNotification::query()->delete();
});

it('does not summarize the same email twice across two summary tasks', function() {
    $taskA = MailboxTask::factory()->create([
        'name'               => 'Tarea A',
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    $taskB = MailboxTask::factory()->create([
        'name'               => 'Tarea B',
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(['Resumen único.']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect(InboundEmail::first()->mailboxTasks()->count())->toBe(1);
    expect($taskA->fresh()->processedEmails)->toHaveCount(1);
    expect($taskB->fresh()->processedEmails)->toHaveCount(0);
    expect($taskA->fresh()->last_run_at)->not->toBeNull();
});

it('still processes emails for watch tasks even if a summary task already claimed them', function() {
    $summary = MailboxTask::factory()->create([
        'name'               => 'Resumen',
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    $watch = MailboxTask::factory()->watch()->create(['name' => 'Vigilancia']);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(['Resumen.', 'Alerta.']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->twice();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($summary->fresh()->processedEmails)->toHaveCount(1);
    expect($watch->fresh()->processedEmails)->toHaveCount(1);
});

it('skips notification when AI returns NO_NOTIFY sentinel', function() {
    $user = TestUser::factory()->create();
    config()->set('sisifo.notify_user_id', $user->id);
    config()->set('sisifo.notifications.notifiable', fn() => TestUser::find($user->id));

    $task = MailboxTask::factory()->create([
        'schedule_frequency'   => 'daily',
        'schedule_time'        => '00:00',
        'last_run_at'          => null,
        'last_result'          => 'Resultado previo importante',
        'notification_methods' => [
            MailboxTaskNotificationEnum::Pushover,
            MailboxTaskNotificationEnum::Database,
        ],
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(['NO_NOTIFY']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->last_run_at)->not->toBeNull();
    expect($task->fresh()->processedEmails)->toHaveCount(0);
    expect($task->fresh()->last_result)->toBe('Resultado previo importante');
    expect($user->notifications()->count())->toBe(0);
});

it('treats NO_NOTIFY with surrounding whitespace as skip', function() {
    $task = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(["  NO_NOTIFY\n"]);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->processedEmails)->toHaveCount(0);
});

it('still notifies when NO_NOTIFY appears only as a substring', function() {
    $task = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(['NO_NOTIFY for routine ones but URGENT: server down.']);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->processedEmails)->toHaveCount(1);
});

it('returns FAILURE exit code when --task run throws', function() {
    $task = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeLlm(fn() => throw new RuntimeException('boom'));

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send')->once();
    $this->app->instance(PushoverService::class, $mock);

    $this->artisan('mailbox:process', ['--task' => $task->id])->assertFailed();
});
