<?php

namespace Arzcode\Sisifo\Models;

use Arzcode\Sisifo\Contracts\SummarizableItem;
use Arzcode\Sisifo\Database\Factories\FeedItemFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A Sisifo-owned feed entry (currently a GitHub release), stored with honest
 * columns and exposed to the engine through the SummarizableItem contract.
 *
 * @property int $id
 * @property string $external_id
 * @property string $title
 * @property string $url
 * @property string $source_ref
 * @property string $body
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FeedItem extends Model implements SummarizableItem
{
    use HasFactory;

    protected $table = 'sisifo_feed_items';
    protected $fillable = [
        'external_id',
        'title',
        'url',
        'source_ref',
        'body',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): FeedItemFactory
    {
        return FeedItemFactory::new();
    }

    public function mailboxTasks(): BelongsToMany
    {
        return $this->belongsToMany(MailboxTask::class, 'mailbox_task_feed_item')
            ->withPivot('processed_at');
    }

    public function sisifoTitle(): string
    {
        return $this->title;
    }

    public function sisifoOrigin(): string
    {
        return $this->source_ref;
    }

    public function sisifoOriginId(): string
    {
        return $this->url;
    }

    public function sisifoBody(): string
    {
        return $this->body;
    }

    public function sisifoOccurredAt(): CarbonInterface
    {
        // published_at is nullable in the schema, but is always populated on
        // ingestion; fall back to now() to honour the non-null contract.
        return $this->published_at ?? now();
    }

    public function sisifoKey(): int
    {
        return $this->id;
    }
}
