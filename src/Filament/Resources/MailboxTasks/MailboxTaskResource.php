<?php

namespace Arzcode\Sisifo\Filament\Resources\MailboxTasks;

use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages\CreateMailboxTask;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages\EditMailboxTask;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Pages\ListMailboxTasks;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Schemas\MailboxTaskForm;
use Arzcode\Sisifo\Filament\Resources\MailboxTasks\Tables\MailboxTasksTable;
use Arzcode\Sisifo\Models\MailboxTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MailboxTaskResource extends Resource
{
    protected static ?string $model = MailboxTask::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;
    protected static ?string $recordTitleAttribute = 'name';
    protected static bool $hasTitleCaseModelLabel = false;

    public static function getModelLabel(): string
    {
        return __('sisifo::sisifo.mailbox_task');
    }

    public static function getPluralLabel(): string
    {
        return __('sisifo::sisifo.mailbox_tasks');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = config('sisifo.navigation_group');

        return is_string($group) ? __($group) : $group;
    }

    public static function getActiveNavigationIcon(): string|Heroicon|null
    {
        $icon = self::getNavigationIcon();

        if ($icon instanceof Heroicon) {
            $iconName = $icon->name;

            if (str_starts_with($iconName, 'Outlined')) {
                $solidName = substr($iconName, 8);

                return constant(Heroicon::class . '::' . $solidName);
            }

            return $icon;
        }

        if (is_string($icon)) {
            return str($icon)->replace('heroicon-o-', 'heroicon-s-')->toString();
        }

        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return MailboxTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailboxTasksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListMailboxTasks::route('/'),
            'create' => CreateMailboxTask::route('/create'),
            'edit'   => EditMailboxTask::route('/{record}/edit'),
        ];
    }
}
