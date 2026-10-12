<?php

namespace Arzcode\Sisifo\Settings;

use Arzcode\Sisifo\Enums\MailboxTaskSourceEnum;
use Spatie\LaravelSettings\Settings;

class MailboxSettings extends Settings
{
    /**
     * Instructions appended to every task's own prompt, keyed by the value of
     * the task's source. Keeping this a map rather than one property per source
     * means a new source contributes nothing until it is given a prompt, and
     * needs no settings migration of its own.
     *
     * @var array<string, string>
     */
    public array $common_prompts;

    public static function group(): string
    {
        return 'mailbox';
    }

    public function promptFor(MailboxTaskSourceEnum $source): string
    {
        return trim($this->common_prompts[$source->value] ?? '');
    }

    public function setPromptFor(MailboxTaskSourceEnum $source, string $prompt): void
    {
        $this->common_prompts = [...$this->common_prompts, $source->value => $prompt];
    }
}
