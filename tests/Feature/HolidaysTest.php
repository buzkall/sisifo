<?php

use Arzcode\Sisifo\Contracts\LlmProvider;
use Arzcode\Sisifo\Enums\MailboxTaskTypeEnum;
use Arzcode\Sisifo\Models\InboundEmail;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Services\Pushover\PushoverService;
use Arzcode\Sisifo\Tests\Support\FakeLlmProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function createHolidaysTable(string $table = 'holidays', string $column = 'date'): void
{
    Schema::create($table, function(Blueprint $table) use ($column) {
        $table->id();
        $table->date($column);
    });
}

beforeEach(function() {
    MailboxTask::query()->delete();
    Cache::put('mailbox:last_fetch', now()->toIso8601String(), now()->addHour());

    $this->llm = new FakeLlmProvider(['Resumen.']);
    app()->instance(LlmProvider::class, $this->llm);

    $pushover = Mockery::mock(PushoverService::class);
    $pushover->shouldReceive('send');
    $this->app->instance(PushoverService::class, $pushover);

    $this->summary = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);
    $this->watch = MailboxTask::factory()->create(['type' => MailboxTaskTypeEnum::Watch]);

    InboundEmail::factory()->create(['received_at' => now()->subHours(2)]);
});

afterEach(function() {
    Schema::dropIfExists('holidays');
    Schema::dropIfExists('festivos');
});

it('runs the tasks when the host app has no holidays table', function() {
    $this->artisan('mailbox:process')->assertSuccessful();

    $this->llm->assertPromptCount(2);
});

it('skips watch and summary tasks when today is a holiday', function() {
    createHolidaysTable();
    DB::table('holidays')->insert(['date' => now()->toDateString()]);

    $this->artisan('mailbox:process')
        ->expectsOutputToContain('Today is a holiday')
        ->assertSuccessful();

    $this->llm->assertNeverPrompted();
    expect($this->summary->fresh()->last_run_at)->toBeNull();
});

it('runs the tasks when the holidays table does not list today', function() {
    createHolidaysTable();
    DB::table('holidays')->insert([
        ['date' => now()->subDay()->toDateString()],
        ['date' => now()->addDay()->toDateString()],
    ]);

    $this->artisan('mailbox:process')->assertSuccessful();

    $this->llm->assertPromptCount(2);
});

it('picks the emails up the day after a holiday', function() {
    createHolidaysTable();
    DB::table('holidays')->insert(['date' => now()->toDateString()]);

    $this->artisan('mailbox:process')->assertSuccessful();
    $this->llm->assertNeverPrompted();

    $this->travel(1)->days();

    $this->artisan('mailbox:process')->assertSuccessful();
    $this->llm->assertPromptCount(2);
});

it('still runs a task requested explicitly on a holiday', function() {
    createHolidaysTable();
    DB::table('holidays')->insert(['date' => now()->toDateString()]);

    $this->artisan('mailbox:process', ['--task' => $this->summary->id])->assertSuccessful();

    $this->llm->assertPromptCount(1);
});

it('reads the configured table and date column', function() {
    createHolidaysTable('festivos', 'day');
    DB::table('festivos')->insert(['day' => now()->toDateString()]);

    config()->set('sisifo.holidays.table', 'festivos');
    config()->set('sisifo.holidays.date_column', 'day');

    $this->artisan('mailbox:process')->assertSuccessful();

    $this->llm->assertNeverPrompted();
});

it('ignores a holidays table without the configured date column', function() {
    createHolidaysTable('holidays', 'day');
    DB::table('holidays')->insert(['day' => now()->toDateString()]);

    $this->artisan('mailbox:process')->assertSuccessful();

    $this->llm->assertPromptCount(2);
});

it('does not check holidays when the table is set to null', function() {
    createHolidaysTable();
    DB::table('holidays')->insert(['date' => now()->toDateString()]);

    config()->set('sisifo.holidays.table', null);

    $this->artisan('mailbox:process')->assertSuccessful();

    $this->llm->assertPromptCount(2);
});
