<?php

use Arzcode\Sisifo\Enums\MailboxTaskNotificationEnum;
use Arzcode\Sisifo\Enums\MailboxTaskSourceEnum;
use Arzcode\Sisifo\Enums\MailboxTaskTypeEnum;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\MailboxTaskResource;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages\CreateMailboxTask;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages\EditMailboxTask;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages\ListMailboxTasks;
use Arzcode\Sisifo\Models\FeedItem;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Settings\MailboxSettings;
use Arzcode\Sisifo\Tests\Support\TestUser;
use Filament\Actions\ReplicateAction;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

test('navigation group defaults to Logistics and follows the config', function() {
    expect(MailboxTaskResource::getNavigationGroup())->toBe(__('Logistics'));

    config()->set('sisifo.navigation_group', 'Operations');
    expect(MailboxTaskResource::getNavigationGroup())->toBe('Operations');

    config()->set('sisifo.navigation_group', null);
    expect(MailboxTaskResource::getNavigationGroup())->toBeNull();
});

test('editCommonPrompt header action persists a separate value per source', function() {
    Livewire::test(ListMailboxTasks::class)
        ->callAction('editCommonPrompt', data: [
            MailboxTaskSourceEnum::InboundEmail->value   => 'Base para correo',
            MailboxTaskSourceEnum::GithubReleases->value => 'Base para releases',
        ])
        ->assertHasNoActionErrors();

    $settings = app(MailboxSettings::class)->refresh();

    expect($settings->promptFor(MailboxTaskSourceEnum::InboundEmail))->toBe('Base para correo')
        ->and($settings->promptFor(MailboxTaskSourceEnum::GithubReleases))->toBe('Base para releases');
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

test('forceFetch header action is hidden on a watch task that reads a feed', function() {
    $watch = MailboxTask::factory()->githubReleases()->watch()->create();

    Livewire::test(EditMailboxTask::class, ['record' => $watch->id])
        ->assertActionHidden('forceFetch');
});

test('fetchReleases header action is visible only on github releases tasks', function() {
    $releases = MailboxTask::factory()->githubReleases()->create();
    $email = MailboxTask::factory()->create();

    Livewire::test(EditMailboxTask::class, ['record' => $releases->id])
        ->assertActionVisible('fetchReleases');

    Livewire::test(EditMailboxTask::class, ['record' => $email->id])
        ->assertActionHidden('fetchReleases');
});

test('fetchReleases header action ingests the feed and then runs the task', function() {
    Http::fake([
        'github.com/*' => Http::response(file_get_contents(__DIR__ . '/../../Fixtures/filament-releases.atom')),
    ]);

    $kernel = Mockery::mock(ConsoleKernel::class);
    $kernel->shouldReceive('call')->once()->with('mailbox:process', Mockery::on(fn($args) => isset($args['--task'])))->andReturn(0);
    $kernel->shouldReceive('output')->andReturn('');
    $kernel->shouldIgnoreMissing();
    $this->app->instance(ConsoleKernel::class, $kernel);

    $task = MailboxTask::factory()->githubReleases()->create();

    Livewire::test(EditMailboxTask::class, ['record' => $task->id])
        ->callAction('fetchReleases')
        ->assertHasNoActionErrors();

    expect(FeedItem::where('source_ref', 'filamentphp/filament')->count())->toBeGreaterThan(0);
});

test('source_ref is only asked for when the source is a github repository', function() {
    Livewire::test(CreateMailboxTask::class)
        ->fillForm(['source' => MailboxTaskSourceEnum::InboundEmail->value])
        ->assertFormFieldHidden('source_ref')
        ->fillForm(['source' => MailboxTaskSourceEnum::GithubReleases->value])
        ->assertFormFieldVisible('source_ref');
});

test('email filters are hidden for a github releases task', function() {
    Livewire::test(CreateMailboxTask::class)
        ->fillForm(['source' => MailboxTaskSourceEnum::InboundEmail->value])
        ->assertFormFieldVisible('filters.from_addresses')
        ->fillForm(['source' => MailboxTaskSourceEnum::GithubReleases->value])
        ->assertFormFieldHidden('filters.from_addresses');
});

test('source_ref must look like owner/repo', function() {
    $data = [
        'name'                 => 'Filament releases',
        'type'                 => MailboxTaskTypeEnum::Summary->value,
        'source'               => MailboxTaskSourceEnum::GithubReleases->value,
        'prompt'               => 'Summarize.',
        'notification_methods' => [MailboxTaskNotificationEnum::Pushover->value],
    ];

    Livewire::test(CreateMailboxTask::class)
        ->fillForm([...$data, 'source_ref' => 'https://github.com/filamentphp/filament'])
        ->call('create')
        ->assertHasFormErrors(['source_ref']);

    Livewire::test(CreateMailboxTask::class)
        ->fillForm([...$data, 'source_ref' => 'filamentphp/filament'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MailboxTask::where('source_ref', 'filamentphp/filament')->exists())->toBeTrue();
});

test('a github releases task cannot be saved without a repository', function() {
    Livewire::test(CreateMailboxTask::class)
        ->fillForm([
            'name'                 => 'Filament releases',
            'type'                 => MailboxTaskTypeEnum::Summary->value,
            'source'               => MailboxTaskSourceEnum::GithubReleases->value,
            'prompt'               => 'Summarize.',
            'notification_methods' => [MailboxTaskNotificationEnum::Pushover->value],
        ])
        ->call('create')
        ->assertHasFormErrors(['source_ref']);
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
