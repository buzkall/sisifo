<?php

use Arzcode\Sisifo\Contracts\LlmProvider;
use Arzcode\Sisifo\Enums\MailboxTaskSourceEnum;
use Arzcode\Sisifo\Models\FeedItem;
use Arzcode\Sisifo\Models\InboundEmail;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Services\Pushover\PushoverService;
use Arzcode\Sisifo\Settings\MailboxSettings;
use Arzcode\Sisifo\Tests\Support\FakeLlmProvider;
use Illuminate\Support\Facades\Http;

function fakeReleasesHttp(): void
{
    $atom = file_get_contents(__DIR__ . '/../Fixtures/filament-releases.atom');

    Http::fake([
        'github.com/*' => Http::response($atom, 200, ['Content-Type' => 'application/atom+xml']),
    ]);
}

function fakeReleasesLlm(array $responses): FakeLlmProvider
{
    $fake = new FakeLlmProvider($responses);
    app()->instance(LlmProvider::class, $fake);

    $mock = Mockery::mock(PushoverService::class);
    $mock->shouldReceive('send');
    app()->instance(PushoverService::class, $mock);

    return $fake;
}

beforeEach(function() {
    MailboxTask::query()->delete();
    Cache::put('mailbox:last_fetch', now()->toIso8601String(), now()->addHour());
});

it('selects and marks a github_releases task\'s feed items via the seam without touching emails', function() {
    $githubTask = MailboxTask::factory()->githubReleases()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    $emailTask = MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeReleasesHttp();
    fakeReleasesLlm(['Resumen releases.', 'Resumen correos.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    // The beta is skipped on ingest, so only the two stable releases land.
    expect(FeedItem::count())->toBe(2)
        ->and($githubTask->fresh()->processedFeedItems)->toHaveCount(2)
        ->and($emailTask->fresh()->processedEmails)->toHaveCount(1);

    // No cross-contamination between the two sources' pivots.
    expect($githubTask->fresh()->processedEmails)->toHaveCount(0)
        ->and($emailTask->fresh()->processedFeedItems)->toHaveCount(0);
});

it('does not reprocess feed items a github_releases task already handled', function() {
    $task = MailboxTask::factory()->githubReleases()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    fakeReleasesHttp();
    fakeReleasesLlm(['Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();
    expect($task->fresh()->processedFeedItems)->toHaveCount(2);

    // A second run finds nothing unprocessed, whether or not it re-fetches.
    $task->update(['last_run_at' => null]);
    $fake = fakeReleasesLlm(['Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->processedFeedItems)->toHaveCount(2);
    $fake->assertNeverPrompted();
});

it('passes a full changelog to the LLM under the source\'s own body budget', function() {
    MailboxTask::factory()->githubReleases()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    fakeReleasesHttp();
    $fake = fakeReleasesLlm(['Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    // The full v5.6.8 changelog (>500 chars) reaches the model intact without
    // touching the global budget.
    expect($fake->calls[0]['input'])->toContain('ENDOFCHANGELOG568');
});

it('keeps the email body budget when the releases budget is raised', function() {
    MailboxTask::factory()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create([
        'received_at' => now()->subHour(),
        'text_body'   => str_repeat('a', 600) . 'ENDOFEMAIL',
    ]);

    $fake = fakeReleasesLlm(['Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($fake->calls[0]['input'])->not->toContain('ENDOFEMAIL');
});

it('falls back to the global body budget when the source has none of its own', function() {
    config()->set('sisifo.sources.github_releases.item_body_budget', null);

    MailboxTask::factory()->githubReleases()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    fakeReleasesHttp();
    $fake = fakeReleasesLlm(['Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($fake->calls[0]['input'])->not->toContain('ENDOFCHANGELOG568');
});

it('fetches a repository at most once per interval however often its tasks run', function() {
    MailboxTask::factory()->githubReleases()->watch()->count(2)->create();

    fakeReleasesHttp();
    fakeReleasesLlm(['Resumen.', 'Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();
    $this->artisan('mailbox:process')->assertSuccessful();

    Http::assertSentCount(1);

    $this->travel(61)->minutes();
    $this->artisan('mailbox:process')->assertSuccessful();

    Http::assertSentCount(2);
});

it('throttles a failing repository instead of retrying it on every run', function() {
    MailboxTask::factory()->githubReleases('nobody/missing')->watch()->create();

    Http::fake(['github.com/*' => Http::response('Not Found', 404)]);
    fakeReleasesLlm([]);

    $this->artisan('mailbox:process')->assertSuccessful();
    $this->artisan('mailbox:process')->assertSuccessful();

    Http::assertSentCount(1);
});

it('notifies the task when a scheduled fetch of its repository fails', function() {
    $task = MailboxTask::factory()->githubReleases('nobody/missing')->watch()->create(['name' => 'Releases']);

    Http::fake(['github.com/*' => Http::response('Not Found', 404)]);
    app()->instance(LlmProvider::class, new FakeLlmProvider([]));

    $pushover = Mockery::mock(PushoverService::class);
    $pushover->shouldReceive('send')
        ->once()
        ->withArgs(fn(string $message) => str_contains($message, 'nobody/missing'));
    app()->instance(PushoverService::class, $pushover);

    // The second run is throttled, so the failure is reported once, not twice.
    $this->artisan('mailbox:process')->assertSuccessful();
    $this->artisan('mailbox:process')->assertSuccessful();
});

it('describes releases to the LLM as releases, and emails as emails', function() {
    MailboxTask::factory()->githubReleases()->create([
        'prompt'             => 'RELEASESPECIFIC',
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    MailboxTask::factory()->create([
        'prompt'             => 'EMAILSPECIFIC',
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeReleasesHttp();
    $fake = fakeReleasesLlm(['Resumen releases.', 'Resumen correos.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    $fake->assertPrompted(fn($prompt) => str_contains($prompt->instructions, 'RELEASESPECIFIC')
        && str_contains($prompt->instructions, 'analyzing the releases'));

    $fake->assertPrompted(fn($prompt) => str_contains($prompt->instructions, 'EMAILSPECIFIC')
        && str_contains($prompt->instructions, 'analyzing the emails'));

    $inputs = collect($fake->calls)->pluck('input');

    expect($inputs->first(fn(string $input) => str_contains($input, 'Repositorio: filamentphp/filament')))
        ->toContain('Versión: v5.6.8')
        ->and($inputs->first(fn(string $input) => str_contains($input, 'Asunto: ')))
        ->not->toContain('Repositorio: ');
});

it('ignores releases published before the task\'s look-back window', function() {
    // v5.6.8 was published on 2026-07-10 and v5.6.7 on 2026-07-05.
    $this->travelTo('2026-07-14 12:00:00');

    $task = MailboxTask::factory()->githubReleases()->create([
        'schedule_frequency' => 'daily',
        'schedule_days'      => null,
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
        'filters'            => ['look_back_days' => 7],
    ]);

    fakeReleasesHttp();
    $fake = fakeReleasesLlm(['Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    // Both are stored, but only the one inside the window is reported.
    expect(FeedItem::count())->toBe(2)
        ->and($task->fresh()->processedFeedItems->pluck('title')->all())->toBe(['v5.6.8'])
        ->and($fake->calls[0]['input'])->not->toContain('Versión: v5.6.7');
});

it('appends each source its own common instructions and never the other source\'s', function() {
    $settings = app(MailboxSettings::class);
    $settings->setPromptFor(MailboxTaskSourceEnum::InboundEmail, 'EMAILBOILER');
    $settings->setPromptFor(MailboxTaskSourceEnum::GithubReleases, 'RELEASEBOILER');
    $settings->save();

    MailboxTask::factory()->githubReleases()->create([
        'prompt'             => 'RELEASESPECIFIC',
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    MailboxTask::factory()->create([
        'prompt'             => 'EMAILSPECIFIC',
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    fakeReleasesHttp();
    $fake = fakeReleasesLlm(['Resumen releases.', 'Resumen correos.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    $fake->assertPrompted(fn($prompt) => str_starts_with($prompt->instructions, "RELEASESPECIFIC\n\nRELEASEBOILER")
        && ! str_contains($prompt->instructions, 'EMAILBOILER'));

    $fake->assertPrompted(fn($prompt) => str_starts_with($prompt->instructions, "EMAILSPECIFIC\n\nEMAILBOILER")
        && ! str_contains($prompt->instructions, 'RELEASEBOILER'));
});
