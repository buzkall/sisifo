<?php

namespace Arzcode\Sisifo\Console\Commands;

use Arzcode\Sisifo\Contracts\LlmProvider;
use Arzcode\Sisifo\Contracts\NotificationChannel;
use Arzcode\Sisifo\Contracts\SummarizableItem;
use Arzcode\Sisifo\Enums\MailboxTaskNotificationEnum;
use Arzcode\Sisifo\Models\InboundEmail;
use Arzcode\Sisifo\Models\MailboxTask;
use Arzcode\Sisifo\Settings\MailboxSettings;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

class ProcessMailbox extends Command
{
    private const SKIP_SENTINEL = 'NO_NOTIFY';

    protected $signature = 'mailbox:process {--task= : Run a specific task by ID, skipping schedule checks}';
    protected $description = 'Fetch new emails and process active mailbox tasks';

    public function handle(LlmProvider $llm): int
    {
        $this->fetchEmailsIfDue();

        if ($taskId = $this->option('task')) {
            $task = MailboxTask::find($taskId);

            if (! $task) {
                $this->error("Task #{$taskId} not found.");

                return self::FAILURE;
            }

            return $this->executeTask($task, $llm) ? self::SUCCESS : self::FAILURE;
        }

        $this->processWatchTasks($llm);
        $this->processSummaryTasks($llm);

        return self::SUCCESS;
    }

    private function fetchEmailsIfDue(): void
    {
        $intervalMinutes = (int)config('sisifo.schedule.check_every_minutes', 15);
        $cacheKey = 'mailbox:last_fetch';

        $lastFetch = Cache::get($cacheKey);

        if ($lastFetch && Carbon::parse($lastFetch)->diffInMinutes(now()) < $intervalMinutes) {
            return;
        }

        $this->info('Checking mailbox...');

        try {
            $this->fetchEmailsWithRetries();
            $this->info('Mailbox check completed.');
        } catch (Exception $e) {
            $this->error("Mailbox check error: {$e->getMessage()}");
            Log::error('Mailbox fetch error: ' . $this->describeThrowable($e), ['exception' => $e]);
        } finally {
            // Arm the throttle whatever the outcome. The command is scheduled
            // every minute, so leaving the key unset after a failure retries
            // the broken connection every 60s and gets the account throttled.
            Cache::put($cacheKey, now()->toIso8601String(), now()->addHours(24));
        }
    }

    /**
     * Retry transient IMAP failures — dropped sockets and read timeouts, which
     * webklex/php-imap reports as AuthFailedException("failed to authenticate")
     * — before giving up. An ImapServerErrorException means the server actively
     * answered NO/BAD/BYE (rejected credentials, too many connections), so it is
     * rethrown immediately instead of hammering the server.
     *
     * @throws Exception
     */
    private function fetchEmailsWithRetries(): void
    {
        $attempts = max(1, (int)config('sisifo.imap.retry_attempts', 3));
        $delaySeconds = max(0, (int)config('sisifo.imap.retry_delay_seconds', 5));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->fetchEmails();

                return;
            } catch (ImapServerErrorException $e) {
                throw $e;
            } catch (Exception $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }

                $this->warn("Mailbox check attempt {$attempt}/{$attempts} failed: {$e->getMessage()}. Retrying...");

                Sleep::for($delaySeconds * $attempt)->seconds();
            }
        }
    }

    private function fetchEmails(): void
    {
        $config = [
            'host'          => config('sisifo.imap.host'),
            'port'          => config('sisifo.imap.port'),
            'encryption'    => config('sisifo.imap.encryption'),
            'validate_cert' => config('sisifo.imap.validate_cert'),
            'username'      => config('sisifo.imap.username'),
            'password'      => config('sisifo.imap.password'),
            'protocol'      => config('sisifo.imap.protocol'),
            'timeout'       => (int)config('sisifo.imap.timeout', 60),
        ];

        $client = app(ClientManager::class)->make($config);

        try {
            $client->connect();

            $this->storeMessages($client->getFolder('INBOX'));
        } finally {
            $this->disconnectQuietly($client);
        }
    }

    private function storeMessages(Folder $folder): void
    {
        $lastEmail = InboundEmail::latest('received_at')->first();
        $messages = $folder->messages()
            ->whereUnseen()
            ->whereSince($lastEmail?->received_at ?? now()->subDay())
            ->get();

        foreach ($messages as $message) {
            try {
                InboundEmail::firstOrCreate(
                    ['message_id' => $message->getMessageId()],
                    [
                        'subject'      => Str::limit((string)$message->getSubject(), 252),
                        'from_address' => $message->getFrom()[0]->mail,
                        'from_name'    => $message->getFrom()[0]->personal ?? '',
                        'message'      => $message->getRawBody(),
                        'text_body'    => $this->extractTextBody($message),
                        'received_at'  => $message->getDate()->toDate(),
                    ]
                );

                $this->info("Email processed: {$message->getSubject()}");
            } catch (Exception $e) {
                $this->error("Email processing error: {$e->getMessage()}");
                Log::error('Email processing error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Releasing the IMAP connection must never mask the error that got us here.
     */
    private function disconnectQuietly(Client $client): void
    {
        try {
            $client->disconnect();
        } catch (Throwable $e) {
            Log::debug('Mailbox disconnect error: ' . $e->getMessage());
        }
    }

    /**
     * Flatten an exception chain into a single line. webklex/php-imap collapses
     * every transient socket failure into AuthFailedException("failed to
     * authenticate"), so only the previous exception says what really happened.
     */
    private function describeThrowable(Throwable $e): string
    {
        $descriptions = [];

        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $descriptions[] = $current::class . ': ' . ($current->getMessage() ?: '(no message)');
        }

        return implode(' <- ', $descriptions);
    }

    private function extractTextBody(Message $message): string
    {
        $plain = trim($message->getTextBody());

        if ($plain !== '') {
            return $plain;
        }

        $html = trim($message->getHTMLBody());

        if ($html === '') {
            return '';
        }

        $converter = new HtmlConverter([
            'strip_tags'   => true,
            'remove_nodes' => 'style script head',
        ]);

        return trim($converter->convert($html));
    }

    private function processWatchTasks(LlmProvider $llm): void
    {
        MailboxTask::active()->watch()->each(fn(MailboxTask $task) => $this->executeTask($task, $llm));
    }

    private function processSummaryTasks(LlmProvider $llm): void
    {
        MailboxTask::active()->summary()->each(function(MailboxTask $task) use ($llm) {
            if (! $task->isDue()) {
                Log::info("Mailbox task [{$task->name}] skipped: {$task->whyNotDue()}");

                return;
            }

            $this->executeTask($task, $llm);
        });
    }

    private function executeTask(MailboxTask $task, LlmProvider $llm): bool
    {
        $items = $task->getUnprocessedItems();

        if ($items->isEmpty()) {
            return true;
        }

        $task->markAttempted();

        try {
            $bodyBudget = (int)config('sisifo.llm.item_body_budget', 500);

            $itemsText = $items->map(function(SummarizableItem $item) use ($bodyBudget) {
                $body = Str::limit($item->sisifoBody(), $bodyBudget);

                return "De: {$item->sisifoOrigin()}\n"
                    . "Asunto: {$item->sisifoTitle()}\n"
                    . "Fecha: {$item->sisifoOccurredAt()->format('d/m/Y H:i')}\n"
                    . "Contenido: {$body}\n";
            })->implode("\n---\n");

            $commonPrompt = trim(app(MailboxSettings::class)->common_prompt);
            $skipInstruction = 'If after analyzing the emails there is nothing relevant to report, respond ONLY with the literal text '
                . self::SKIP_SENTINEL . ' and nothing else.';
            $fullPrompt = trim(trim($task->prompt)
                . ($commonPrompt === '' ? '' : "\n\n" . $commonPrompt)
                . "\n\n" . $skipInstruction);

            $previousResult = $task->last_result
                ? "\n\n---\nResultado del último envío (para comparar y destacar solo lo nuevo):\n" . Str::limit($task->last_result, 1024)
                : '';

            $maxTokens = (int)config('sisifo.llm.max_tokens', 2048);

            $responseText = $llm->text($fullPrompt, $itemsText . $previousResult, $maxTokens);

            if (trim($responseText) === self::SKIP_SENTINEL) {
                $this->info("Task \"{$task->name}\" returned no notification.");

                return true;
            }

            $summary = Str::limit($responseText, 1024);

            $this->sendNotification($task, $summary);

            $task->markItemsAsProcessed($items);
            $task->update(['last_result' => $summary]);

            if ($task->one_shot) {
                $task->update(['is_active' => false]);
            }

            $this->info("Task \"{$task->name}\" executed successfully.");

            return true;
        } catch (Exception $e) {
            $this->error("Task \"{$task->name}\" error: {$e->getMessage()}");
            Log::error("Mailbox task [{$task->name}] error: " . $e->getMessage());

            $this->notifyFailure($task, $e->getMessage());

            return false;
        }
    }

    private function sendNotification(MailboxTask $task, string $message): void
    {
        $task->notification_methods?->each(function(MailboxTaskNotificationEnum $method) use ($task, $message) {
            $this->resolveChannel($method)->send($task, $task->name, $message, (bool)$task->is_urgent);
        });
    }

    private function notifyFailure(MailboxTask $task, string $error): void
    {
        $title = __('sisifo::sisifo.error_title', ['name' => $task->name]);
        $body = Str::limit($error, 512);

        $task->notification_methods?->each(function(MailboxTaskNotificationEnum $method) use ($task, $title, $body) {
            try {
                $this->resolveChannel($method)->send($task, $title, $body, true);
            } catch (Exception $e) {
                Log::error("Mailbox task [{$task->name}] failed to notify: " . $e->getMessage());
            }
        });
    }

    private function resolveChannel(MailboxTaskNotificationEnum $method): NotificationChannel
    {
        return app($method->toChannelClass());
    }
}
