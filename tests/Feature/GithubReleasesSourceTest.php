<?php

use Arzcode\Sisifo\Contracts\LlmProvider;
use Arzcode\Sisifo\Models\FeedItem;
use Arzcode\Sisifo\Models\InboundEmail;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Services\Pushover\PushoverService;
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

    // A second run re-fetches (deduped) and finds nothing unprocessed.
    $task->update(['last_run_at' => null]);
    $fake = fakeReleasesLlm(['Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    expect($task->fresh()->processedFeedItems)->toHaveCount(2);
    $fake->assertNeverPrompted();
});

it('passes a full changelog to the LLM when the body budget is raised', function() {
    config()->set('sisifo.llm.item_body_budget', 50000);

    MailboxTask::factory()->githubReleases()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    fakeReleasesHttp();
    $fake = fakeReleasesLlm(['Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    // The full v5.6.8 changelog (>500 chars) reaches the model intact.
    expect($fake->calls[0]['input'])->toContain('ENDOFCHANGELOG568');
});

it('truncates the changelog at the default 500-char budget', function() {
    MailboxTask::factory()->githubReleases()->create([
        'schedule_frequency' => 'daily',
        'schedule_time'      => '00:00',
        'last_run_at'        => null,
    ]);

    fakeReleasesHttp();
    $fake = fakeReleasesLlm(['Resumen.']);

    $this->artisan('mailbox:process')->assertSuccessful();

    // With the email-era default budget, the end marker is cut off.
    expect($fake->calls[0]['input'])->not->toContain('ENDOFCHANGELOG568');
});
