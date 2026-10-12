<?php

use Arzcode\Sisifo\Enums\MailboxTaskSourceEnum;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class SplitMailboxCommonPromptBySource extends SettingsMigration
{
    /**
     * Each source now carries its own common instructions. Rename rather than
     * add + delete so the text already written by the host app survives, then
     * seed every source with it; they are edited apart from here on.
     */
    public function up(): void
    {
        $this->migrator->rename('mailbox.common_prompt', 'mailbox.common_prompts');

        $this->migrator->update(
            'mailbox.common_prompts',
            fn(string $prompt): array => collect(MailboxTaskSourceEnum::cases())
                ->mapWithKeys(fn(MailboxTaskSourceEnum $source): array => [$source->value => $prompt])
                ->all(),
        );
    }

    public function down(): void
    {
        $this->migrator->update(
            'mailbox.common_prompts',
            fn(array $prompts): string => $prompts[MailboxTaskSourceEnum::InboundEmail->value] ?? '',
        );

        $this->migrator->rename('mailbox.common_prompts', 'mailbox.common_prompt');
    }
}
