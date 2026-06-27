<?php

namespace Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages;

use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Actions\ForceFetchEmailsAction;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Actions\RunMailboxTaskAction;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\MailboxTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMailboxTask extends EditRecord
{
    protected static string $resource = MailboxTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RunMailboxTaskAction::make(),
            ForceFetchEmailsAction::make(),
            DeleteAction::make(),
        ];
    }
}
