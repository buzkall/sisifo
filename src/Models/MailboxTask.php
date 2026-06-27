<?php

namespace Arzcode\Sisifo\Models;

use Arzcode\Sisifo\Database\Factories\MailboxTaskFactory;
use Arzcode\Sisifo\Enums\MailboxTaskNotificationEnum;
use Arzcode\Sisifo\Enums\MailboxTaskTypeEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class MailboxTask extends Model
{
    use HasFactory;

    protected $table = 'mailbox_tasks';
    protected $fillable = [
        'name',
        'type',
        'prompt',
        'is_active',
        'one_shot',
        'schedule_frequency',
        'schedule_days',
        'schedule_time',
        'schedule_timezone',
        'filters',
        'notification_methods',
        'is_urgent',
        'last_run_at',
        'last_result',
    ];

    protected function casts(): array
    {
        return [
            'type'                 => MailboxTaskTypeEnum::class,
            'notification_methods' => AsEnumCollection::of(MailboxTaskNotificationEnum::class),
            'schedule_days'        => 'array',
            'filters'              => 'array',
            'is_active'            => 'boolean',
            'one_shot'             => 'boolean',
            'is_urgent'            => 'boolean',
            'last_run_at'          => 'datetime',
        ];
    }

    protected static function newFactory(): MailboxTaskFactory
    {
        return MailboxTaskFactory::new();
    }

    public function processedEmails(): BelongsToMany
    {
        return $this->belongsToMany(InboundEmail::class, 'mailbox_task_inbound_email')
            ->withPivot('processed_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSummary(Builder $query): Builder
    {
        return $query->where('type', MailboxTaskTypeEnum::Summary);
    }

    public function scopeWatch(Builder $query): Builder
    {
        return $query->where('type', MailboxTaskTypeEnum::Watch);
    }

    public function getUnprocessedEmails(): Collection
    {
        $query = InboundEmail::whereDoesntHave(
            'mailboxTasks',
            fn(Builder $q) => $this->type === MailboxTaskTypeEnum::Summary
                ? $q->where('type', MailboxTaskTypeEnum::Summary->value)
                : $q->where('mailbox_task_id', $this->id)
        );

        $this->applyFilters($query);

        $lookBackDays = $this->filters['look_back_days'] ?? 7;
        $query->receivedAfter(now()->subDays($lookBackDays));

        return $query->orderBy('received_at')->get();
    }

    public function isDue(): bool
    {
        if ($this->type === MailboxTaskTypeEnum::Watch) {
            return true;
        }

        $now = now($this->schedule_timezone);

        return match ($this->schedule_frequency) {
            'daily'  => $this->isDayAllowed($now) && $this->isTimeReached($now) && $this->hasNotRunToday($now),
            'hourly' => $this->isDayAllowed($now) && $this->hasNotRunInLastMinutes(60),
            default  => false,
        };
    }

    public function whyNotDue(): ?string
    {
        if ($this->type === MailboxTaskTypeEnum::Watch) {
            return null;
        }

        $now = now($this->schedule_timezone);

        return match ($this->schedule_frequency) {
            'daily' => match (true) {
                ! $this->isDayAllowed($now)   => 'day not allowed',
                ! $this->isTimeReached($now)  => "time not reached (scheduled {$this->schedule_time} {$this->schedule_timezone})",
                ! $this->hasNotRunToday($now) => 'already ran today',
                default                       => null,
            },
            'hourly' => match (true) {
                ! $this->isDayAllowed($now)         => 'day not allowed',
                ! $this->hasNotRunInLastMinutes(60) => 'ran in the last 60 minutes',
                default                             => null,
            },
            default => "unsupported frequency [{$this->schedule_frequency}]",
        };
    }

    public function markAttempted(): void
    {
        $this->update(['last_run_at' => now()]);
    }

    public function markEmailsAsProcessed(Collection $emails): void
    {
        $pivotData = $emails->mapWithKeys(fn(InboundEmail $email) => [
            $email->id => ['processed_at' => now()],
        ]);

        $this->processedEmails()->attach($pivotData);

        $this->update(['last_run_at' => now()]);
    }

    private function applyFilters(Builder $query): void
    {
        $filters = collect($this->filters ?? [])
            ->map(fn(mixed $value) => collect($value))
            ->filter(fn(Collection $value) => $value->isNotEmpty());

        $query
            ->when(
                $filters->get('from_addresses'),
                fn(Builder $q, Collection $addresses) => $q->whereIn('from_address', $addresses)
            )
            ->when(
                $filters->get('from_domains'),
                fn(Builder $q, Collection $domains) => $q
                    ->where(fn(Builder $q) => $domains
                        ->each(fn(string $domain) => $q->orWhereLike('from_address', "%@$domain")))
            )
            ->when(
                $filters->get('subject_keywords'),
                fn(Builder $q, Collection $keywords) => $q
                    ->where(fn(Builder $q) => $keywords
                        ->each(fn(string $keyword) => $q->orWhereLike('subject', "%$keyword%")))
            );
    }

    private function isDayAllowed(Carbon $now): bool
    {
        if (empty($this->schedule_days)) {
            return true;
        }

        return in_array($now->dayOfWeekIso, $this->schedule_days);
    }

    private function isTimeReached(Carbon $now): bool
    {
        if (! $this->schedule_time) {
            return true;
        }

        $scheduledTime = Carbon::parse($this->schedule_time, $this->schedule_timezone);

        return $now->gte($scheduledTime);
    }

    private function hasNotRunToday(Carbon $now): bool
    {
        if (! $this->last_run_at) {
            return true;
        }

        return ! $this->last_run_at->setTimezone($this->schedule_timezone)->isSameDay($now);
    }

    private function hasNotRunInLastMinutes(int $minutes): bool
    {
        if (! $this->last_run_at) {
            return true;
        }

        return $this->last_run_at->diffInMinutes(now()) >= $minutes;
    }
}
