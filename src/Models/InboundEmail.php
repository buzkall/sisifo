<?php

namespace Arzcode\Sisifo\Models;

use Arzcode\Sisifo\Contracts\SummarizableItem;
use Arzcode\Sisifo\Database\Factories\InboundEmailFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $message_id
 * @property string $subject
 * @property string $from_address
 * @property string $from_name
 * @property string $message
 * @property string $text_body
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InboundEmail extends Model implements SummarizableItem
{
    use HasFactory;

    protected $table = 'mailbox_inbound_emails';
    protected $fillable = [
        'message_id',
        'subject',
        'from_address',
        'from_name',
        'message',
        'text_body',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    protected static function newFactory(): InboundEmailFactory
    {
        return InboundEmailFactory::new();
    }

    public function mailboxTasks(): BelongsToMany
    {
        return $this->belongsToMany(MailboxTask::class, 'mailbox_task_inbound_email')
            ->withPivot('processed_at');
    }

    public function scopeReceivedAfter(Builder $query, $date): Builder
    {
        return $query->where('received_at', '>=', $date);
    }

    public function sisifoTitle(): string
    {
        return $this->subject;
    }

    public function sisifoOrigin(): string
    {
        return "{$this->from_name} <{$this->from_address}>";
    }

    public function sisifoOriginId(): string
    {
        return $this->from_address;
    }

    public function sisifoBody(): string
    {
        return $this->text_body;
    }

    public function sisifoOccurredAt(): CarbonInterface
    {
        // received_at is nullable on the mail table, but is always populated for
        // fetched emails; fall back to now() to honour the non-null contract.
        return $this->received_at ?? now();
    }

    public function sisifoKey(): int
    {
        return $this->id;
    }
}
