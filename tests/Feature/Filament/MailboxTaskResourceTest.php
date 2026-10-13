<?php

use Arzcode\Sisifo\Filament\Resources\MailboxTasks\MailboxTaskResource;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages\EditMailboxTask;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages\ListMailboxTasks;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Settings\MailboxSettings;
use Arzcode\Sisifo\Tests\Support\TestUser;
use Filament\Actions\ReplicateAction;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function() {
    $this->user = TestUser::factory()->create();
    actingAs($this->user);

    MailboxTask::query()->delete();
});

test('ListMailboxTasks page renders', function() {
    Livewire::test(ListMailboxTasks::class)->assertSuccessful();
});

test('ListMailboxTasks page says nothing about holidays without a holidays table', function() {
    Livewire::test(ListMailboxTasks::class)
        ->assertDontSee('holiday');
});

test('ListMailboxTasks page warns that tasks are skipped on a holiday', function() {
    Schema::create('holidays', function(Blueprint $table) {
        $table->id();
        $table->date('date');
    });
    DB::table('holidays')->insert(['date' => now()->toDateString()]);

    Livewire::test(ListMailboxTasks::class)
        ->assertSee('Today (29/06/2026) is a holiday: scheduled tasks are skipped.');

    Schema::drop('holidays');
});

test('ListMailboxTasks page announces the next holiday', function() {
    Schema::create('holidays', function(Blueprint $table) {
        $table->id();
        $table->date('date');
    });
    DB::table('holidays')->insert([
        ['date' => now()->subDay()->toDateString()],
        ['date' => now()->addDays(20)->toDateString()],
        ['date' => now()->addDays(3)->toDateString()],
    ]);

    Livewire::test(ListMailboxTasks::class)
        ->assertSee('Scheduled tasks will be skipped on the next holiday: 02/07/2026.')
        ->assertDontSee('is a holiday');

    Schema::drop('holidays');
});

test('navigation group defaults to Logistics and follows the config', function() {
    expect(MailboxTaskResource::getNavigationGroup())->toBe(__('Logistics'));

    config()->set('sisifo.navigation_group', 'Operations');
    expect(MailboxTaskResource::getNavigationGroup())->toBe('Operations');

    config()->set('sisifo.navigation_group', null);
    expect(MailboxTaskResource::getNavigationGroup())->toBeNull();
});

test('editCommonPrompt header action persists the new value in settings', function() {
    Livewire::test(ListMailboxTasks::class)
        ->callAction('editCommonPrompt', data: ['common_prompt' => 'Nueva base compartida'])
        ->assertHasNoActionErrors();

    expect(app(MailboxSettings::class)->refresh()->common_prompt)->toBe('Nueva base compartida');
});

test('ReplicateAction clones the task with copy suffix, inactive, and reset history', function() {
    $task = MailboxTask::factory()->create([
        'name'        => 'Resumen diario',
        'is_active'   => true,
        'last_run_at' => now()->subHour(),
        'last_result' => 'Resultado anterior',
    ]);

    Livewire::test(ListMailboxTasks::class)
        ->callTableAction(ReplicateAction::class, $task)
        ->assertHasNoTableActionErrors();

    $replica = MailboxTask::where('name', '!=', 'Resumen diario')->where('name', 'like', 'Resumen diario%')->first();

    expect($replica)->not->toBeNull()
        ->and($replica->is_active)->toBeFalse()
        ->and($replica->last_run_at)->toBeNull()
        ->and($replica->last_result)->toBeNull();
});

test('EditMailboxTask page escapes the last_result callout', function() {
    $task = MailboxTask::factory()->create([
        'last_result' => '<script>alert(1)</script>',
    ]);

    Livewire::test(EditMailboxTask::class, ['record' => $task->id])
        ->assertSuccessful()
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

test('EditMailboxTask page renders allowed formatting tags in the last_result callout', function() {
    $task = MailboxTask::factory()->create([
        'last_result' => '<b>DICTAPP</b><br><i>nota</i>',
    ]);

    Livewire::test(EditMailboxTask::class, ['record' => $task->id])
        ->assertSuccessful()
        ->assertSee('<b>DICTAPP</b>', escape: false)
        ->assertSee('<i>nota</i>', escape: false);
});

test('forceFetch header action is visible only on watch tasks', function() {
    $watch = MailboxTask::factory()->watch()->create();
    $summary = MailboxTask::factory()->create();

    Livewire::test(EditMailboxTask::class, ['record' => $watch->id])
        ->assertActionVisible('forceFetch');

    Livewire::test(EditMailboxTask::class, ['record' => $summary->id])
        ->assertActionHidden('forceFetch');
});

test('forceFetch header action clears the IMAP fetch cache before running mailbox:process', function() {
    Cache::put('mailbox:last_fetch', now()->toIso8601String(), now()->addHours(24));

    $kernel = Mockery::mock(ConsoleKernel::class);
    $kernel->shouldReceive('call')->once()->with('mailbox:process', Mockery::on(fn($args) => isset($args['--task'])))->andReturn(0);
    $kernel->shouldReceive('output')->andReturn('');
    $kernel->shouldIgnoreMissing();
    $this->app->instance(ConsoleKernel::class, $kernel);

    $watch = MailboxTask::factory()->watch()->create();

    Livewire::test(EditMailboxTask::class, ['record' => $watch->id])
        ->callAction('forceFetch')
        ->assertHasNoActionErrors();

    expect(Cache::has('mailbox:last_fetch'))->toBeFalse();
});
