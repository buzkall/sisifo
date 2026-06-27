<?php

namespace Arzcode\Sisifo\Models;

use Arzcode\Sisifo\Database\Factories\InboundEmailFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InboundEmail extends Model
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
}
