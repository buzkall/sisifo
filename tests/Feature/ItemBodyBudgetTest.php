<?php

use Arzcode\Sisifo\Contracts\LlmProvider;
use Arzcode\Sisifo\Models\InboundEmail;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Services\Pushover\PushoverService;
use Arzcode\Sisifo\Tests\Support\FakeLlmProvider;

function bindBudgetLlm(): FakeLlmProvider
{
    $fake = new FakeLlmProvider(['Resumen.']);
    app()->instance(LlmProvider::class, $fake);

    // The task still notifies once; keep Pushover inert.
    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send');
    app()->instance(PushoverService::class, $mock);

    return $fake;
}

beforeEach(function() {
    MailboxTask::query()->delete();
    Cache::put('mailbox:last_fetch', now()->toIso8601String(), now()->addHour());
});

it('truncates item bodies to 500 characters by default', function() {
    MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create([
        'text_body'   => str_repeat('a', 700),
        'received_at' => now()->subHour(),
    ]);

    $fake = bindBudgetLlm();

    $this->artisan('mailbox:process')->assertSuccessful();

    $input = $fake->calls[0]['input'];

    // Str::limit keeps 500 chars then appends the ellipsis, so a run of 501 'a'
    // never appears while the 500-char run followed by '...' does.
    expect($input)->toContain(str_repeat('a', 500) . '...')
        ->and($input)->not->toContain(str_repeat('a', 501));
});

it('does not truncate when the body budget is raised above the body length', function() {
    config()->set('sisifo.llm.item_body_budget', 2000);

    MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create([
        'subject'      => 'Report',
        'from_name'    => 'Ada',
        'from_address' => 'ada@example.com',
        'text_body'    => str_repeat('a', 700),
        'received_at'  => now()->subHour(),
    ]);

    $fake = bindBudgetLlm();

    $this->artisan('mailbox:process')->assertSuccessful();

    $input = $fake->calls[0]['input'];

    expect($input)->toContain(str_repeat('a', 700))
        ->and($input)->not->toContain('...');
});

it('passes the configured max_tokens to the LLM', function() {
    config()->set('sisifo.llm.max_tokens', 4096);

    MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    $fake = bindBudgetLlm();

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($fake->calls[0]['max_tokens'])->toBe(4096);
});
