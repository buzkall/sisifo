<?php

use Arzcode\Sisifo\Contracts\SummarizableItem;
use Arzcode\Sisifo\Enums\MailboxTaskSourceEnum;
use Arzcode\Sisifo\Enums\MailboxTaskTypeEnum;
use Arzcode\Sisifo\Models\InboundEmail;
use Arzcode\Sisifo\Models\MailboxTask;
use Illuminate\Support\Facades\DB;

it('defaults the source column to inbound_email at the database level', function() {
    // Insert without a source so the migration default is what fills the column.
    $id = DB::table('mailbox_tasks')->insertGetId([
        'name'       => 'Sourceless task',
        'type'       => MailboxTaskTypeEnum::Summary->value,
        'prompt'     => 'Summarize.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(MailboxTask::findOrFail($id)->source)->toBe(MailboxTaskSourceEnum::InboundEmail);
});

it('selects unprocessed items through the source-neutral method', function() {
    $task = MailboxTask::factory()->create();

    InboundEmail::factory()->count(2)->create(['received_at' => now()->subHour()]);

    $items = $task->getUnprocessedItems();

    expect($items)->toHaveCount(2)
        ->and($items->every(fn($item) => $item instanceof SummarizableItem))->toBeTrue();
});

it('marks items as processed through the source-neutral method', function() {
    $task = MailboxTask::factory()->create();

    InboundEmail::factory()->count(2)->create(['received_at' => now()->subHour()]);

    $items = $task->getUnprocessedItems();
    $task->markItemsAsProcessed($items);

    expect($task->fresh()->processedEmails)->toHaveCount(2)
        ->and($task->fresh()->last_run_at)->not->toBeNull()
        ->and($task->getUnprocessedItems())->toHaveCount(0);
});

it('does not reselect items already processed by another summary task', function() {
    $taskA = MailboxTask::factory()->create(['name' => 'A']);
    $taskB = MailboxTask::factory()->create(['name' => 'B']);

    InboundEmail::factory()->create(['received_at' => now()->subHour()]);

    $taskA->markItemsAsProcessed($taskA->getUnprocessedItems());

    expect($taskA->fresh()->processedEmails)->toHaveCount(1)
        ->and($taskB->getUnprocessedItems())->toHaveCount(0);
});

// The enum cast rejects an unknown source at assignment, so a typo can never
// reach the dispatch in getUnprocessedItems() in the first place.
it('rejects an unknown source', function() {
    MailboxTask::factory()->create(['source' => 'github_release']);
})->throws(ValueError::class);

it('still accepts the raw string constants for backwards compatibility', function() {
    $task = MailboxTask::factory()->create(['source' => MailboxTask::SOURCE_GITHUB_RELEASES]);

    expect($task->fresh()->source)->toBe(MailboxTaskSourceEnum::GithubReleases);
});
